<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProveedorResource\Pages;

use App\Filament\Resources\ProveedorResource;
use App\Services\ProveedorService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProveedor extends CreateRecord
{
    protected static string $resource = ProveedorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProveedorService::class)->crear($data);
    }
}
