<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCompra;
use App\Enums\EstadoSerie;
use App\Enums\TipoMovimiento;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventarioService
{
    /**
     * @param array<int, array<string>> $seriesPorDetalle Array asociativo [detalle_id => ['SN1', 'SN2', ...]]
     */
    public function recibirCompra(Compra $compra, array $seriesPorDetalle, User $usuario): Compra
    {
        if ($compra->estado !== EstadoCompra::BORRADOR) {
            throw new RuntimeException('Solo se pueden recibir compras en estado borrador.');
        }

        return DB::transaction(function () use ($compra, $seriesPorDetalle, $usuario) {
            // Bloqueamos la compra para evitar recepción concurrente
            $compraBloqueada = Compra::query()->lockForUpdate()->find($compra->id);
            if ($compraBloqueada->estado !== EstadoCompra::BORRADOR) {
                throw new RuntimeException('La compra ya no está en estado borrador.');
            }

            $compra->load(['detalles.producto']);

            // Lista para validar duplicados en el envío de esta recepción
            $seriesEnviadas = [];

            foreach ($compra->detalles as $detalle) {
                $producto = Producto::query()->lockForUpdate()->find($detalle->producto_id);
                $seriesItem = $seriesPorDetalle[$detalle->id] ?? [];

                if ($producto->controla_series) {
                    if (count($seriesItem) !== $detalle->cantidad) {
                        throw new InvalidArgumentException(
                            "La cantidad de series enviadas (" . count($seriesItem) . ") no coincide con la cantidad del detalle ({$detalle->cantidad}) para el producto {$producto->nombre}."
                        );
                    }

                    foreach ($seriesItem as $snOriginal) {
                        $sn = strtoupper(trim((string) $snOriginal));
                        if ($sn === '') {
                            throw new InvalidArgumentException('El número de serie no puede estar vacío.');
                        }

                        $claveSerie = "{$producto->id}:{$sn}";
                        if (isset($seriesEnviadas[$claveSerie])) {
                            throw new InvalidArgumentException("La serie '{$sn}' está duplicada en el envío para el producto {$producto->nombre}.");
                        }
                        $seriesEnviadas[$claveSerie] = true;

                        // Validar contra la base de datos
                        $existe = Serie::query()
                            ->where('producto_id', $producto->id)
                            ->where('numero_serie', $sn)
                            ->exists();

                        if ($existe) {
                            throw new InvalidArgumentException("La serie '{$sn}' ya se encuentra registrada en la base de datos para el producto {$producto->nombre}.");
                        }

                        // Crear serie en estado disponible
                        $serie = Serie::create([
                            'producto_id' => $producto->id,
                            'compra_detalle_id' => $detalle->id,
                            'numero_serie' => $sn,
                            'estado' => EstadoSerie::DISPONIBLE,
                            'costo' => $detalle->costo_unitario,
                            'fecha_ingreso' => Carbon::now(),
                            'observaciones' => "Ingreso por compra {$compra->codigo}",
                        ]);

                        // Calcular stock resultante (cada serie suma +1 al stock disponible)
                        $stockActual = $this->stockDisponible($producto);

                        MovimientoInventario::create([
                            'producto_id' => $producto->id,
                            'serie_id' => $serie->id,
                            'tipo' => TipoMovimiento::INGRESO,
                            'cantidad' => 1,
                            'stock_resultante' => $stockActual,
                            'referencia_tipo' => Compra::class,
                            'referencia_id' => $compra->id,
                            'motivo' => "Ingreso por recepción de compra {$compra->codigo}",
                            'user_id' => $usuario->id,
                        ]);
                    }
                } else {
                    // Producto sin control de series: movimiento por cantidad directa
                    $stockPrevio = $this->stockDisponible($producto);
                    $nuevoStock = $stockPrevio + $detalle->cantidad;

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => null,
                        'tipo' => TipoMovimiento::INGRESO,
                        'cantidad' => $detalle->cantidad,
                        'stock_resultante' => $nuevoStock,
                        'referencia_tipo' => Compra::class,
                        'referencia_id' => $compra->id,
                        'motivo' => "Ingreso por recepción de compra {$compra->codigo}",
                        'user_id' => $usuario->id,
                    ]);
                }
            }

            $compra->update([
                'estado' => EstadoCompra::RECIBIDA,
                'recibido_por' => $usuario->id,
                'recibido_en' => Carbon::now(),
            ]);

            return $compra->fresh(['detalles.series']);
        });
    }

    public function stockDisponible(Producto $producto): int
    {
        if ($producto->controla_series) {
            return (int) Serie::query()
                ->where('producto_id', $producto->id)
                ->where('estado', EstadoSerie::DISPONIBLE)
                ->count();
        }

        return (int) MovimientoInventario::query()
            ->where('producto_id', $producto->id)
            ->sum('cantidad');
    }

    public function buscarSerie(string $numeroSerie, ?int $productoId = null): ?Serie
    {
        $sn = strtoupper(trim($numeroSerie));

        $query = Serie::query()->where('numero_serie', $sn);
        if ($productoId !== null) {
            $query->where('producto_id', $productoId);
        }

        return $query->first();
    }

    /**
     * @return Collection<int, MovimientoInventario>
     */
    public function historialSerie(Serie $serie): Collection
    {
        return MovimientoInventario::query()
            ->where('serie_id', $serie->id)
            ->with(['usuario', 'producto'])
            ->orderBy('id', 'asc')
            ->get();
    }

    public function darDeBaja(Serie $serie, string $motivo, User $usuario): Serie
    {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('El motivo de baja es obligatorio.');
        }

        if ($serie->estado !== EstadoSerie::DISPONIBLE) {
            throw new RuntimeException("Solo se pueden dar de baja series en estado disponible (estado actual: {$serie->estado->value}).");
        }

        return DB::transaction(function () use ($serie, $motivo, $usuario) {
            $serieBloqueada = Serie::query()->lockForUpdate()->find($serie->id);
            if ($serieBloqueada->estado !== EstadoSerie::DISPONIBLE) {
                throw new RuntimeException("La serie ya no se encuentra en estado disponible.");
            }

            $serie->update([
                'estado' => EstadoSerie::BAJA,
                'observaciones' => trim(($serie->observaciones ?? '') . "\nBaja: " . $motivo),
            ]);

            $producto = Producto::query()->lockForUpdate()->find($serie->producto_id);
            $stockActual = $this->stockDisponible($producto);

            MovimientoInventario::create([
                'producto_id' => $producto->id,
                'serie_id' => $serie->id,
                'tipo' => TipoMovimiento::SALIDA,
                'cantidad' => -1,
                'stock_resultante' => $stockActual,
                'referencia_tipo' => Serie::class,
                'referencia_id' => $serie->id,
                'motivo' => "Baja manual de serie: {$motivo}",
                'user_id' => $usuario->id,
            ]);

            return $serie->fresh();
        });
    }

    public function ajustarEstado(Serie $serie, EstadoSerie $nuevoEstado, string $motivo, User $usuario): Serie
    {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('El motivo del ajuste es obligatorio.');
        }

        if (! $usuario->hasRole('admin')) {
            throw new RuntimeException('Solo un usuario administrador puede ajustar el estado de una serie.');
        }

        if (! $serie->estado->puedePasarA($nuevoEstado)) {
            throw new RuntimeException("Transición inválida: no se puede pasar una serie de estado '{$serie->estado->value}' a '{$nuevoEstado->value}'.");
        }

        return DB::transaction(function () use ($serie, $nuevoEstado, $motivo, $usuario) {
            $estadoAnterior = $serie->estado;

            $serie->update([
                'estado' => $nuevoEstado,
                'observaciones' => trim(($serie->observaciones ?? '') . "\nAjuste a {$nuevoEstado->value}: {$motivo}"),
            ]);

            $producto = Producto::query()->lockForUpdate()->find($serie->producto_id);
            $stockActual = $this->stockDisponible($producto);

            // Determinar cambio de cantidad en el stock disponible
            $delta = 0;
            if ($estadoAnterior === EstadoSerie::DISPONIBLE && $nuevoEstado !== EstadoSerie::DISPONIBLE) {
                $delta = -1;
            } elseif ($estadoAnterior !== EstadoSerie::DISPONIBLE && $nuevoEstado === EstadoSerie::DISPONIBLE) {
                $delta = 1;
            }

            MovimientoInventario::create([
                'producto_id' => $producto->id,
                'serie_id' => $serie->id,
                'tipo' => TipoMovimiento::AJUSTE,
                'cantidad' => $delta,
                'stock_resultante' => $stockActual,
                'referencia_tipo' => Serie::class,
                'referencia_id' => $serie->id,
                'motivo' => "Ajuste administrativo de '{$estadoAnterior->value}' a '{$nuevoEstado->value}': {$motivo}",
                'user_id' => $usuario->id,
            ]);

            return $serie->fresh();
        });
    }
}
