<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

class MarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('marcas.ver');
    }

    public function view(User $user, Marca $marca): bool
    {
        return $user->can('marcas.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('marcas.crear');
    }

    public function update(User $user, Marca $marca): bool
    {
        return $user->can('marcas.editar');
    }

    public function delete(User $user, Marca $marca): bool
    {
        return $user->can('marcas.eliminar');
    }
}
