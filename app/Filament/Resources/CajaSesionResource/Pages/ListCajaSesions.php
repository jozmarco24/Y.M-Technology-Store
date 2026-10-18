<?php

namespace App\Filament\Resources\CajaSesionResource\Pages;

use App\Filament\Resources\CajaSesionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCajaSesions extends ListRecords
{
    protected static string $resource = CajaSesionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
