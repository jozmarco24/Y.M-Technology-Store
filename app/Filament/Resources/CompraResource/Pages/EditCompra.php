<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Models\Compra;
use App\Services\CompraService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $detalles = $data['detalles'] ?? null;
        unset($data['detalles']);

        /** @var Compra $record */
        return app(CompraService::class)->actualizarBorrador($record, $data, $detalles);
    }
}
