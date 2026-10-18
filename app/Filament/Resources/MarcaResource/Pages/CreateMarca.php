<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarcaResource\Pages;

use App\Filament\Resources\MarcaResource;
use App\Services\MarcaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMarca extends CreateRecord
{
    protected static string $resource = MarcaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(MarcaService::class)->crear($data);
    }
}
