<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if ($user->hasRole('vendedor')) {
                return redirect()->route('pos.index');
            }

            return redirect('/admin');
        }

        return view('pos.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        // Redirección según rol: vendedor -> /pos, admin/almacen -> /admin
        if ($user->hasRole('vendedor')) {
            return redirect()->intended(route('pos.index'));
        }

        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pos.login');
    }
}
