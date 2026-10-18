<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Devolucion;
use App\Models\User;

class DevolucionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermissionTo('devoluciones.solicitar')
            || $user->hasPermissionTo('devoluciones.aprobar');
    }

    public function view(User $user, Devolucion $devolucion): bool
    {
        if ($user->hasRole('admin') || $user->hasPermissionTo('devoluciones.aprobar')) {
            return true;
        }

        // Vendedor solo ve las que solicitó o de su propia tienda
        return $user->hasPermissionTo('devoluciones.solicitar') && $devolucion->solicitado_por === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('devoluciones.solicitar');
    }

    public function approve(User $user, Devolucion $devolucion): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('devoluciones.aprobar');
    }

    public function complete(User $user, Devolucion $devolucion): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('devoluciones.completar');
    }

    public function update(User $user, Devolucion $devolucion): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Devolucion $devolucion): bool
    {
        return false;
    }
}
