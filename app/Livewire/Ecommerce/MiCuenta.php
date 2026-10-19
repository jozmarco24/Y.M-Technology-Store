<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\DireccionCliente;
use App\Models\Garantia;
use App\Models\Pedido;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class MiCuenta extends Component
{
    public string $pestana = 'pedidos'; // pedidos, direcciones, garantias, perfil

    public string $nombre = '';

    public string $telefono = '';

    // Nueva direccion
    public string $alias = '';

    public string $destinatario = '';

    public string $telefonoDir = '';

    public string $departamento = 'LIMA';

    public string $provincia = 'LIMA';

    public string $distrito = '';

    public string $direccion = '';

    public string $referencia = '';

    public bool $esPrincipal = false;

    public function mount(): void
    {
        $cliente = auth('cliente')->user();
        if (! $cliente) {
            $this->redirect(route('cliente.login'));

            return;
        }

        $this->nombre = $cliente->nombre;
        $this->telefono = $cliente->telefono ?? '';
        $this->destinatario = $cliente->nombre;
        $this->telefonoDir = $cliente->telefono ?? '';
    }

    public function cambiarPestana(string $pestana): void
    {
        $this->pestana = $pestana;
    }

    public function guardarPerfil(): void
    {
        $this->validate([
            'nombre' => 'required|string|min:3',
            'telefono' => 'nullable|string|min:6',
        ]);

        $cliente = auth('cliente')->user();
        if ($cliente) {
            $cliente->update([
                'nombre' => $this->nombre,
                'telefono' => $this->telefono,
            ]);

            $this->dispatch('notificar', [
                'tipo' => 'success',
                'mensaje' => 'Datos de perfil actualizados.',
            ]);
        }
    }

    public function agregarDireccion(): void
    {
        $this->validate([
            'alias' => 'required|string|max:50',
            'destinatario' => 'required|string|max:100',
            'telefonoDir' => 'required|string|max:20',
            'distrito' => 'required|string|max:50',
            'direccion' => 'required|string|max:255',
        ]);

        $cliente = auth('cliente')->user();
        if ($cliente) {
            if ($this->esPrincipal) {
                $cliente->direcciones()->update(['es_principal' => false]);
            }

            $cliente->direcciones()->create([
                'alias' => $this->alias,
                'destinatario' => $this->destinatario,
                'telefono' => $this->telefonoDir,
                'departamento' => $this->departamento,
                'provincia' => $this->provincia,
                'distrito' => $this->distrito,
                'direccion' => $this->direccion,
                'referencia' => $this->referencia,
                'es_principal' => $this->esPrincipal,
            ]);

            $this->reset(['alias', 'distrito', 'direccion', 'referencia', 'esPrincipal']);
            $this->dispatch('notificar', [
                'tipo' => 'success',
                'mensaje' => 'Dirección agregada correctamente.',
            ]);
        }
    }

    public function eliminarDireccion(int $id): void
    {
        $cliente = auth('cliente')->user();
        if ($cliente) {
            $cliente->direcciones()->where('id', $id)->delete();
            $this->dispatch('notificar', [
                'tipo' => 'info',
                'mensaje' => 'Dirección eliminada.',
            ]);
        }
    }

    public function render(): View
    {
        $cliente = auth('cliente')->user();

        /** @var Collection<int, Pedido> $pedidos */
        $pedidos = $cliente ? Pedido::where('cliente_web_id', $cliente->id)->latest('id')->get() : collect();

        /** @var Collection<int, DireccionCliente> $direcciones */
        $direcciones = $cliente ? $cliente->direcciones()->get() : collect();

        // Garantías del cliente asociadas a través de su cliente POS
        /** @var Collection<int, Garantia> $garantias */
        $garantias = collect();
        if ($cliente && $cliente->cliente_id) {
            $garantias = Garantia::with(['serie.producto'])
                ->where('cliente_id', $cliente->cliente_id)
                ->latest('id')
                ->get();
        }

        return view('livewire.ecommerce.mi-cuenta', [
            'pedidos' => $pedidos,
            'direcciones' => $direcciones,
            'garantias' => $garantias,
        ])->layout('layouts.ecommerce', [
            'metaTitle' => 'Mi Cuenta | Panel de Cliente | SYS-COPM',
            'metaDescription' => 'Gestiona tus pedidos, direcciones de entrega y consulta tus garantías oficiales.',
        ]);
    }
}
