<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoriaResource\Pages;

use App\Filament\Resources\CategoriaResource;
use App\Services\CategoriaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategoria extends CreateRecord
{
    protected static string $resource = CategoriaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CategoriaService::class)->crear($data);
    }
}
