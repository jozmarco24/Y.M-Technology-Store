<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CajaSesion;
use App\Models\User;

class CajaSesionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('caja.ver_todas') || $user->can('caja.abrir');
    }

    public function view(User $user, CajaSesion $cajaSesion): bool
    {
        if ($user->can('caja.ver_todas')) {
            return true;
        }

        return $cajaSesion->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('caja.abrir');
    }

    public function update(User $user, CajaSesion $cajaSesion): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('caja.cerrar') && $cajaSesion->user_id === $user->id;
    }

    public function delete(User $user, CajaSesion $cajaSesion): bool
    {
        return false;
    }
}
