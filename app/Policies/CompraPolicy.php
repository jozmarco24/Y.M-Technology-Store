<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoCompra;
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
        return $user->can('compras.crear') && $compra->estado === EstadoCompra::BORRADOR;
    }

    public function recibir(User $user, Compra $compra): bool
    {
        return $user->can('compras.recibir') && $compra->estado === EstadoCompra::BORRADOR;
    }

    public function anular(User $user, Compra $compra): bool
    {
        return $user->can('compras.anular') && $compra->estado !== EstadoCompra::ANULADA;
    }

    public function delete(User $user, Compra $compra): bool
    {
        return false;
    }
}
