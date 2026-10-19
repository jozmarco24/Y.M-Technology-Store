<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrdenServicioResource\Pages;

use App\Filament\Resources\OrdenServicioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrdenesServicio extends ListRecords
{
    protected static string $resource = OrdenServicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kanban')
                ->label('Ver Tablero Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('primary')
                ->url(route('filament.admin.resources.orden-servicios.kanban')),
        ];
    }
}
