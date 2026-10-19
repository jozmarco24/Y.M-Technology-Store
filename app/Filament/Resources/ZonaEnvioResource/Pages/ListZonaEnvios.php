<?php

namespace App\Filament\Resources\ZonaEnvioResource\Pages;

use App\Filament\Resources\ZonaEnvioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZonaEnvios extends ListRecords
{
    protected static string $resource = ZonaEnvioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
