<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarcaResource\Pages;

use App\Filament\Resources\MarcaResource;
use App\Models\Marca;
use App\Services\MarcaService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMarca extends EditRecord
{
    protected static string $resource = MarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Marca $record */
        return app(MarcaService::class)->actualizar($record, $data);
    }
}
