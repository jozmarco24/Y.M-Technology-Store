<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\EstadoComprobante;
use App\Jobs\EnviarComprobanteJob;
use App\Models\ComprobanteElectronico;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ComprobantesPendientes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Facturación Electrónica';

    protected static ?string $navigationLabel = 'Comprobantes Pendientes o Error';

    protected static ?string $title = 'Comprobantes Pendientes o con Error de Envío';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.comprobantes-pendientes';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && $user->hasRole('admin');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ComprobanteElectronico::whereIn('estado', [
            EstadoComprobante::PENDIENTE,
            EstadoComprobante::ERROR,
            EstadoComprobante::RECHAZADO,
        ])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $rechazados = ComprobanteElectronico::where('estado', EstadoComprobante::RECHAZADO)->count();

        return $rechazados > 0 ? 'danger' : 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ComprobanteElectronico::query()
                    ->whereIn('estado', [
                        EstadoComprobante::PENDIENTE,
                        EstadoComprobante::ENVIANDO,
                        EstadoComprobante::ERROR,
                        EstadoComprobante::RECHAZADO,
                    ])
                    ->with(['venta.cliente', 'venta.vendedor'])
                    ->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('venta.codigo')
                    ->label('Venta')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state)),

                Tables\Columns\TextColumn::make('comprobante_completo')
                    ->label('Serie-Número')
                    ->state(fn (ComprobanteElectronico $record): string => "{$record->serie}-{$record->numero}")
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('venta.cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('venta.total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoComprobante $state): string => match ($state) {
                        EstadoComprobante::PENDIENTE => 'warning',
                        EstadoComprobante::ENVIANDO => 'info',
                        EstadoComprobante::ACEPTADO => 'success',
                        EstadoComprobante::RECHAZADO => 'danger',
                        EstadoComprobante::ERROR => 'danger',
                    })
                    ->formatStateUsing(fn (EstadoComprobante $state) => strtoupper($state->value)),

                Tables\Columns\TextColumn::make('intentos')
                    ->label('Intentos')
                    ->alignment('center')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mensaje_sunat')
                    ->label('Mensaje / Error')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('ultimo_intento_en')
                    ->label('Último Intento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('reintentar')
                    ->label('Reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reintentar Envío a SUNAT')
                    ->modalDescription('¿Desea despachar el reintento del comprobante a la cola de facturación?')
                    ->action(function (ComprobanteElectronico $record) {
                        EnviarComprobanteJob::dispatch($record->venta_id)
                            ->onQueue(config('facturacion.cola', 'facturacion'));

                        Notification::make()
                            ->title('Job despachado')
                            ->body("El comprobante {$record->serie}-{$record->numero} fue enviado a la cola para su reintento.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('reintentar_masivo')
                    ->label('Reintentar Seleccionados')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $count = 0;
                        /** @var ComprobanteElectronico $record */
                        foreach ($records as $record) {
                            EnviarComprobanteJob::dispatch($record->venta_id)
                                ->onQueue(config('facturacion.cola', 'facturacion'));
                            $count++;
                        }

                        Notification::make()
                            ->title('Reintento masivo despachado')
                            ->body("Se despacharon {$count} comprobantes a la cola de facturación.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
