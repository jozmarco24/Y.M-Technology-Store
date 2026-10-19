<?php

declare(strict_types=1);

namespace App\Filament\Resources\PoliticaGarantiaResource\Pages;

use App\Filament\Resources\PoliticaGarantiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPoliticaGarantia extends EditRecord
{
    protected static string $resource = PoliticaGarantiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
