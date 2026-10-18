<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCompra;
use App\Enums\EstadoSerie;
use App\Enums\TipoMovimiento;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CompraService
{
    /**
     * @param array<string, mixed> $datos
     * @param array<int, array<string, mixed>> $detalles
     */
    public function crearBorrador(array $datos, array $detalles, User $usuario): Compra
    {
        if (empty($detalles)) {
            throw new InvalidArgumentException('Una compra debe tener al menos un detalle.');
        }

        return DB::transaction(function () use ($datos, $detalles, $usuario) {
            $codigo = $this->generarCodigoCorrelativo();

            $totales = $this->calcularTotales($detalles);

            $datosCompra = array_merge($datos, [
                'codigo' => $codigo,
                'subtotal' => $totales['subtotal'],
                'igv' => $totales['igv'],
                'total' => $totales['total'],
                'estado' => EstadoCompra::BORRADOR,
                'creado_por' => $usuario->id,
            ]);

            $compra = Compra::create($datosCompra);

            foreach ($detalles as $item) {
                $costoUnitario = (float) $item['costo_unitario'];
                $cantidad = (int) $item['cantidad'];
                $subtotal = round($costoUnitario * $cantidad, 2);

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ]);
            }

            return $compra->load('detalles');
        });
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<int, array<string, mixed>>|null $detalles
     */
    public function actualizarBorrador(Compra $compra, array $datos, ?array $detalles = null): Compra
    {
        if ($compra->estado !== EstadoCompra::BORRADOR) {
            throw new RuntimeException('Solo se pueden actualizar compras en estado borrador.');
        }

        return DB::transaction(function () use ($compra, $datos, $detalles) {
            if ($detalles !== null) {
                if (empty($detalles)) {
                    throw new InvalidArgumentException('Una compra debe tener al menos un detalle.');
                }

                $totales = $this->calcularTotales($detalles);
                $datos['subtotal'] = $totales['subtotal'];
                $datos['igv'] = $totales['igv'];
                $datos['total'] = $totales['total'];

                $compra->detalles()->delete();

                foreach ($detalles as $item) {
                    $costoUnitario = (float) $item['costo_unitario'];
                    $cantidad = (int) $item['cantidad'];
                    $subtotal = round($costoUnitario * $cantidad, 2);

                    CompraDetalle::create([
                        'compra_id' => $compra->id,
                        'producto_id' => $item['producto_id'],
                        'cantidad' => $cantidad,
                        'costo_unitario' => $costoUnitario,
                        'subtotal' => $subtotal,
                    ]);
                }
            }

            // Evitamos modificar el código correlativo y el estado en esta acción
            unset($datos['codigo'], $datos['estado'], $datos['creado_por']);

            $compra->update($datos);

            return $compra->fresh(['detalles']);
        });
    }

    public function anular(Compra $compra, string $motivo, User $usuario): Compra
    {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('El motivo de anulación es obligatorio.');
        }

        if ($compra->estado === EstadoCompra::ANULADA) {
            throw new RuntimeException('La compra ya se encuentra anulada.');
        }

        return DB::transaction(function () use ($compra, $motivo, $usuario) {
            if ($compra->estado === EstadoCompra::BORRADOR) {
                $compra->update([
                    'estado' => EstadoCompra::ANULADA,
                    'observaciones' => trim(($compra->observaciones ?? '') . "\nAnulada: " . $motivo),
                ]);

                return $compra->fresh();
            }

            if ($compra->estado === EstadoCompra::RECIBIDA) {
                // Verificar que todas las series sigan en estado disponible
                $compra->load(['detalles.series', 'detalles.producto']);

                foreach ($compra->detalles as $detalle) {
                    if ($detalle->producto->controla_series) {
                        foreach ($detalle->series as $serie) {
                            if ($serie->estado !== EstadoSerie::DISPONIBLE) {
                                throw new RuntimeException(
                                    "No se puede anular la compra recibida porque la serie {$serie->numero_serie} ya no está disponible (estado actual: {$serie->estado->value})."
                                );
                            }
                        }
                    }
                }

                // Si todas proceden, pasar series a 'baja' y registrar movimientos de anulación
                foreach ($compra->detalles as $detalle) {
                    $producto = Producto::query()->lockForUpdate()->find($detalle->producto_id);

                    if ($producto->controla_series) {
                        foreach ($detalle->series as $serie) {
                            $serie->update(['estado' => EstadoSerie::BAJA]);

                            $stockActual = (new InventarioService())->stockDisponible($producto);

                            MovimientoInventario::create([
                                'producto_id' => $producto->id,
                                'serie_id' => $serie->id,
                                'tipo' => TipoMovimiento::ANULACION,
                                'cantidad' => -1,
                                'stock_resultante' => $stockActual,
                                'referencia_tipo' => Compra::class,
                                'referencia_id' => $compra->id,
                                'motivo' => "Anulación de compra {$compra->codigo}: {$motivo}",
                                'user_id' => $usuario->id,
                            ]);
                        }
                    } else {
                        // Producto sin control de series: reduce stock por cantidad
                        $stockActual = (new InventarioService())->stockDisponible($producto);
                        $nuevoStock = $stockActual - $detalle->cantidad;

                        MovimientoInventario::create([
                            'producto_id' => $producto->id,
                            'serie_id' => null,
                            'tipo' => TipoMovimiento::ANULACION,
                            'cantidad' => -$detalle->cantidad,
                            'stock_resultante' => $nuevoStock,
                            'referencia_tipo' => Compra::class,
                            'referencia_id' => $compra->id,
                            'motivo' => "Anulación de compra {$compra->codigo}: {$motivo}",
                            'user_id' => $usuario->id,
                        ]);
                    }
                }

                $compra->update([
                    'estado' => EstadoCompra::ANULADA,
                    'observaciones' => trim(($compra->observaciones ?? '') . "\nAnulada: " . $motivo),
                ]);

                return $compra->fresh();
            }

            throw new RuntimeException("Estado de compra no válido para anulación: {$compra->estado->value}");
        });
    }

    /**
     * @param array<int, array<string, mixed>> $detalles
     * @return array{subtotal: float, igv: float, total: float}
     */
    public function calcularTotales(array $detalles): array
    {
        $subtotal = 0.0;

        foreach ($detalles as $item) {
            $costo = (float) $item['costo_unitario'];
            $cantidad = (int) $item['cantidad'];

            if ($costo < 0) {
                throw new InvalidArgumentException('El costo unitario no puede ser negativo.');
            }

            if ($cantidad <= 0) {
                throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
            }

            $subtotal += round($costo * $cantidad, 2);
        }

        $subtotal = round($subtotal, 2);
        $igv = round($subtotal * 0.18, 2);
        $total = round($subtotal + $igv, 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    public function generarCodigoCorrelativo(): string
    {
        // En PostgreSQL usamos lock exclusivo a nivel de tabla o SELECT con bloqueo para máxima concurrencia
        DB::statement('LOCK TABLE compras IN SHARE ROW EXCLUSIVE MODE');

        $ultimoNumero = DB::table('compras')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(codigo FROM 5) AS INTEGER)), 0) as max_codigo")
            ->value('max_codigo');

        $siguiente = ((int) $ultimoNumero) + 1;

        return sprintf('COM-%06d', $siguiente);
    }
}
