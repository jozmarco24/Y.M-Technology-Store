<?php

namespace App\Filament\Resources\CajaSesionResource\Pages;

use App\Filament\Resources\CajaSesionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCajaSesion extends ViewRecord
{
    protected static string $resource = CajaSesionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
