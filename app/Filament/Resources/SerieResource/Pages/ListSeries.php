<?php

declare(strict_types=1);

namespace App\Filament\Resources\SerieResource\Pages;

use App\Filament\Resources\SerieResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSeries extends ListRecords
{
    protected static string $resource = SerieResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['producto.categoria', 'compraDetalle.compra']);
    }
}
