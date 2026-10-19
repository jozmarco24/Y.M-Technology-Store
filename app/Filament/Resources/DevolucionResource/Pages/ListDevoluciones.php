<?php

declare(strict_types=1);

namespace App\Filament\Resources\DevolucionResource\Pages;

use App\Filament\Resources\DevolucionResource;
use Filament\Resources\Pages\ListRecords;

class ListDevoluciones extends ListRecords
{
    protected static string $resource = DevolucionResource::class;
}
