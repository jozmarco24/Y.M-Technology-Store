<?php

declare(strict_types=1);

namespace App\Filament\Resources\PoliticaGarantiaResource\Pages;

use App\Filament\Resources\PoliticaGarantiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPoliticasGarantia extends ListRecords
{
    protected static string $resource = PoliticaGarantiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
