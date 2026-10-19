<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrdenServicioResource\Pages;

use App\Enums\EstadoOrdenServicio;
use App\Filament\Resources\OrdenServicioResource;
use App\Models\OrdenServicio;
use App\Services\OrdenServicioService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * @property-read array<string, array{titulo: string, color: string, estado: EstadoOrdenServicio}> $columnas
 */
class KanbanOrdenesServicio extends Page
{
    protected static string $resource = OrdenServicioResource::class;

    protected static string $view = 'filament.resources.orden-servicio-resource.pages.kanban-ordenes-servicio';

    protected static ?string $title = 'Tablero Kanban de Servicio Técnico';

    public function getColumnasProperty(): array
    {
        return [
            EstadoOrdenServicio::RECIBIDO->value => [
                'titulo' => 'Recibido',
                'color' => 'gray',
                'estado' => EstadoOrdenServicio::RECIBIDO,
            ],
            EstadoOrdenServicio::DIAGNOSTICO->value => [
                'titulo' => 'En Diagnóstico',
                'color' => 'info',
                'estado' => EstadoOrdenServicio::DIAGNOSTICO,
            ],
            EstadoOrdenServicio::ESPERANDO_APROBACION->value => [
                'titulo' => 'Esperando Aprobación',
                'color' => 'warning',
                'estado' => EstadoOrdenServicio::ESPERANDO_APROBACION,
            ],
            EstadoOrdenServicio::EN_REPARACION->value => [
                'titulo' => 'En Reparación',
                'color' => 'primary',
                'estado' => EstadoOrdenServicio::EN_REPARACION,
            ],
            EstadoOrdenServicio::REPARADO->value => [
                'titulo' => 'Reparado / Listo',
                'color' => 'success',
                'estado' => EstadoOrdenServicio::REPARADO,
            ],
            EstadoOrdenServicio::ENTREGADO->value => [
                'titulo' => 'Entregado',
                'color' => 'success',
                'estado' => EstadoOrdenServicio::ENTREGADO,
            ],
        ];
    }

    public function getOrdenesPorEstado(): array
    {
        $user = Auth::user();
        $query = OrdenServicio::query()->with(['cliente', 'producto', 'serie', 'tecnico']);

        if ($user && $user->hasRole('tecnico') && ! $user->hasRole('admin')) {
            $query->where('tecnico_id', $user->id);
        }

        $todas = $query->latest('id')->get();

        $agrupadas = [];
        foreach ($this->columnas as $estadoKey => $colData) {
            $agrupadas[$estadoKey] = $todas->where('estado.value', $estadoKey);
        }

        return $agrupadas;
    }

    public function moverEstado(int $ordenId, string $nuevoEstadoValor): void
    {
        $orden = OrdenServicio::find($ordenId);
        if (! $orden) {
            Notification::make()->title('Orden no encontrada')->danger()->send();

            return;
        }

        $nuevoEstado = EstadoOrdenServicio::tryFrom($nuevoEstadoValor);
        if (! $nuevoEstado) {
            Notification::make()->title('Estado no válido')->danger()->send();

            return;
        }

        try {
            app(OrdenServicioService::class)->cambiarEstado(
                $orden,
                $nuevoEstado,
                'Cambio de columna en el tablero Kanban',
                Auth::user()
            );

            Notification::make()
                ->title("Orden {$orden->codigo} movida a {$nuevoEstado->etiqueta()}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Transición denegada')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
