<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MovimientoInventario;
use App\Models\User;

class MovimientoInventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('movimientos.ver');
    }

    public function view(User $user, MovimientoInventario $movimiento): bool
    {
        return $user->can('movimientos.ver');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MovimientoInventario $movimiento): bool
    {
        return false;
    }

    public function delete(User $user, MovimientoInventario $movimiento): bool
    {
        return false;
    }
}
