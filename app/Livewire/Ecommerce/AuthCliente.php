<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\ClienteWeb;
use App\Services\CarritoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AuthCliente extends Component
{
    public string $modo = 'login'; // login o registro

    // Login
    public string $email = '';

    public string $password = '';

    public bool $recordar = true;

    // Registro
    public string $nombre = '';

    public string $telefono = '';

    public string $passwordConfirmation = '';

    public function toggleModo(): void
    {
        $this->modo = $this->modo === 'login' ? 'registro' : 'login';
        $this->resetErrorBag();
    }

    public function login(CarritoService $carritoService): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login-cliente:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intenta nuevamente en {$segundos} segundos.",
            ]);
        }

        if (Auth::guard('cliente')->attempt(['email' => $this->email, 'password' => $this->password, 'activo' => true], $this->recordar)) {
            RateLimiter::clear($throttleKey);
            session()->regenerate();

            /** @var ClienteWeb $cliente */
            $cliente = Auth::guard('cliente')->user();

            // Fusionar carrito de invitado si existía en cookie
            $tokenInvitado = (string) Cookie::get('carrito_token');
            if ($tokenInvitado !== '') {
                $carritoService->fusionarCarritoInvitado($tokenInvitado, $cliente);
                Cookie::queue(Cookie::forget('carrito_token'));
            }

            $this->redirect(route('mi-cuenta'));

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $this->addError('email', 'Las credenciales ingresadas son incorrectas o la cuenta está inactiva.');
    }

    public function registrar(CarritoService $carritoService): void
    {
        $this->validate([
            'nombre' => 'required|string|min:3|max:100',
            'email' => 'required|email|unique:clientes_web,email',
            'telefono' => 'nullable|string|min:6|max:20',
            'password' => 'required|string|min:8|same:passwordConfirmation',
        ]);

        $throttleKey = 'registro-cliente:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos de registro. Espera {$segundos} segundos.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        /** @var ClienteWeb $cliente */
        $cliente = ClienteWeb::create([
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono ?: null,
            'password' => Hash::make($this->password),
            'activo' => true,
        ]);

        Auth::guard('cliente')->login($cliente);
        session()->regenerate();

        // Fusionar carrito si existía
        $tokenInvitado = (string) Cookie::get('carrito_token');
        if ($tokenInvitado !== '') {
            $carritoService->fusionarCarritoInvitado($tokenInvitado, $cliente);
            Cookie::queue(Cookie::forget('carrito_token'));
        }

        $this->redirect(route('mi-cuenta'));
    }

    public function logout(): void
    {
        Auth::guard('cliente')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('inicio'));
    }

    public function render(): View
    {
        return view('livewire.ecommerce.auth-cliente')
            ->layout('layouts.ecommerce', [
                'metaTitle' => ($this->modo === 'login' ? 'Iniciar Sesión' : 'Crear Cuenta').' | SYS-COPM',
                'metaDescription' => 'Accede a tu cuenta de cliente para gestionar pedidos y garantías.',
            ]);
    }
}
