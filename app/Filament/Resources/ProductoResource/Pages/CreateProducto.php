<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductoResource\Pages;

use App\Filament\Resources\ProductoResource;
use App\Services\ProductoService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProductoService::class)->crear($data);
    }
}
