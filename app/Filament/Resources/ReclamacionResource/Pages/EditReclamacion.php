<?php

namespace App\Filament\Resources\ReclamacionResource\Pages;

use App\Filament\Resources\ReclamacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReclamacion extends EditRecord
{
    protected static string $resource = ReclamacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
