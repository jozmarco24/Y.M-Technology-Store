<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Serie;
use App\Models\User;

class SeriePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('series.ver');
    }

    public function view(User $user, Serie $serie): bool
    {
        return $user->can('series.ver');
    }

    public function create(User $user): bool
    {
        // Las series solo se crean a través de la recepción de compras
        return false;
    }

    public function update(User $user, Serie $serie): bool
    {
        return false;
    }

    public function darBaja(User $user, Serie $serie): bool
    {
        return $user->can('series.dar_baja') && $serie->estado->value === 'disponible';
    }

    public function ajustarEstado(User $user, Serie $serie): bool
    {
        return $user->can('series.ajustar_estado') && $user->hasRole('admin');
    }

    public function delete(User $user, Serie $serie): bool
    {
        return false;
    }
}
