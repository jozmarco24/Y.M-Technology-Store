<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientoInventarioResource\Pages;

use App\Filament\Resources\MovimientoInventarioResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMovimientoInventarios extends ListRecords
{
    protected static string $resource = MovimientoInventarioResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['producto', 'serie', 'usuario'])
            ->orderBy('id', 'desc');
    }
}
