<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clientes.gestionar') || $user->hasRole(['admin', 'almacen']);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.gestionar') || $user->hasRole(['admin', 'almacen']);
    }

    public function create(User $user): bool
    {
        return $user->can('clientes.gestionar') || $user->hasRole('admin');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.gestionar') || $user->hasRole('admin');
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin');
    }
}
