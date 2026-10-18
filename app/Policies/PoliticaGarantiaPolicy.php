<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PoliticaGarantia;
use App\Models\User;

class PoliticaGarantiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('politicas_garantia.gestionar');
    }

    public function view(User $user, PoliticaGarantia $politica): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('politicas_garantia.gestionar');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('politicas_garantia.gestionar');
    }

    public function update(User $user, PoliticaGarantia $politica): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('politicas_garantia.gestionar');
    }

    public function delete(User $user, PoliticaGarantia $politica): bool
    {
        // La política general por defecto no debe eliminarse
        if ($politica->esGeneral()) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermissionTo('politicas_garantia.gestionar');
    }
}
