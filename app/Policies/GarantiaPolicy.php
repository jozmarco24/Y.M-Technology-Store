<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Garantia;
use App\Models\User;

class GarantiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('garantias.ver');
    }

    public function view(User $user, Garantia $garantia): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('garantias.ver');
    }

    public function create(User $user): bool
    {
        return false; // Las garantías se generan automáticamente desde VentaService
    }

    public function update(User $user, Garantia $garantia): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Garantia $garantia): bool
    {
        return false; // Inmutables, solo anulación por anulación de venta
    }
}
