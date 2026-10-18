<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('compras.ver');
    }

    public function view(User $user, Compra $compra): bool
    {
        return $user->can('compras.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('compras.crear');
    }

    public function update(User $user, Compra $compra): bool
    {
        return $user->can('compras.crear') && $compra->estado->value === 'borrador';
    }

    public function recibir(User $user, Compra $compra): bool
    {
        return $user->can('compras.recibir') && $compra->estado->value === 'borrador';
    }

    public function anular(User $user, Compra $compra): bool
    {
        return $user->can('compras.anular') && $compra->estado->value !== 'anulada';
    }

    public function delete(User $user, Compra $compra): bool
    {
        return false;
    }
}
