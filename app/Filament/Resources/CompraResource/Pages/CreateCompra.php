<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Services\CompraService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $detalles = $data['detalles'] ?? [];
        unset($data['detalles']);

        return app(CompraService::class)->crearBorrador($data, $detalles, auth()->user());
    }
}
