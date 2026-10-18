<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    /**
     * Determina si el usuario puede ver listados de ventas.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ventas.ver');
    }

    /**
     * Determina si el usuario puede ver una venta específica.
     * Vendedores solo pueden ver las propias; admin ve todas.
     */
    public function view(User $user, Venta $venta): bool
    {
        if (! $user->hasPermissionTo('ventas.ver')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $venta->vendedor_id === $user->id;
    }

    /**
     * Determina si el usuario puede crear ventas.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ventas.crear');
    }

    /**
     * Las ventas no se actualizan una vez registradas.
     */
    public function update(User $user, Venta $venta): bool
    {
        return false;
    }

    /**
     * Las ventas no se eliminan físicamente por usuarios.
     */
    public function delete(User $user, Venta $venta): bool
    {
        return false;
    }

    /**
     * Determina si el usuario puede anular una venta (solo admin con permiso).
     */
    public function anular(User $user, Venta $venta): bool
    {
        if (! $user->hasPermissionTo('ventas.anular')) {
            return false;
        }

        return $venta->esCompletada();
    }
}
