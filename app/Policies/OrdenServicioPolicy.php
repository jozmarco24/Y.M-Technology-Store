<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrdenServicio;
use App\Models\User;

class OrdenServicioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('servicio.ver');
    }

    public function view(User $user, OrdenServicio $orden): bool
    {
        return $user->can('servicio.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('servicio.recibir');
    }

    public function diagnosticar(User $user, OrdenServicio $orden): bool
    {
        return $user->can('servicio.diagnosticar');
    }

    public function cobrar(User $user, OrdenServicio $orden): bool
    {
        return $user->can('servicio.cobrar');
    }

    public function entregar(User $user, OrdenServicio $orden): bool
    {
        return $user->can('servicio.entregar');
    }

    public function forzarTipo(User $user): bool
    {
        return $user->can('servicio.forzar_tipo');
    }
}
