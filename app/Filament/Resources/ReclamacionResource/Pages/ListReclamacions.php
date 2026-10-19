<?php

namespace App\Filament\Resources\ReclamacionResource\Pages;

use App\Filament\Resources\ReclamacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReclamacions extends ListRecords
{
    protected static string $resource = ReclamacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
