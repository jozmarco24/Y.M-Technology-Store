<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoGarantia;
use App\Enums\EstadoSerie;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoMovimiento;
use App\Models\CambioSerie;
use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\Garantia;
use App\Models\MovimientoInventario;
use App\Models\Serie;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CambioService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected GarantiaService $garantiaService
    ) {}

    /**
     * Realizar el cambio de una serie por otra nueva.
     *
     * - La nueva serie debe estar DISPONIBLE (bloqueada con lockForUpdate), pasa a VENDIDA.
     * - La original sigue su destino en la devolución.
     * - Si el producto nuevo es distinto o tiene precio diferente, calcula diferencia_monto.
     * - Regla de oro: Si es por defecto de fábrica y es el mismo producto, NO cobra nada (diferencia 0).
     * - Crea nueva garantía para la nueva serie: por defecto se reinicia el plazo, salvo configuración de heredar.
     *
     * @return array{cambio: CambioSerie, garantia: Garantia, diferencia: float}
     */
    public function cambiarSerie(
        Devolucion $devolucion,
        Serie $serieOriginal,
        Serie $serieNueva,
        User $usuario,
        ?float $precioPactadoNueva = null
    ): array {
        return DB::transaction(function () use ($devolucion, $serieOriginal, $serieNueva, $usuario, $precioPactadoNueva) {
            // 1. Bloquear y verificar serie nueva con lockForUpdate
            $nuevaBloqueada = Serie::where('id', $serieNueva->id)->lockForUpdate()->firstOrFail();

            if ($nuevaBloqueada->estado !== EstadoSerie::DISPONIBLE) {
                throw new RuntimeException("La serie nueva '{$nuevaBloqueada->numero_serie}' ya no está disponible (estado: {$nuevaBloqueada->estado->value}).");
            }

            if ($nuevaBloqueada->id === $serieOriginal->id) {
                throw new InvalidArgumentException('La serie de reemplazo no puede ser la misma serie que se devuelve.');
            }

            $venta = $devolucion->venta;
            $detalleOriginal = $devolucion->detalles()->where('serie_id', $serieOriginal->id)->first();
            $precioOriginal = $detalleOriginal ? (float) $detalleOriginal->monto_devuelto : (float) $serieOriginal->producto->precio_venta;

            $productoNuevo = $nuevaBloqueada->producto;
            $precioNuevo = $precioPactadoNueva !== null ? $precioPactadoNueva : (float) $productoNuevo->precio_venta;

            // Regla de oro: si es defecto de fábrica y es el mismo producto, diferencia = 0
            $diferenciaMonto = 0.0;
            if ($devolucion->motivo === MotivoDevolucion::DEFECTO_FABRICA && $serieOriginal->producto_id === $nuevaBloqueada->producto_id) {
                $diferenciaMonto = 0.0;
            } else {
                $diferenciaMonto = round($precioNuevo - $precioOriginal, 2);
            }

            // 2. Pasar serie nueva a VENDIDA
            $nuevaBloqueada->update([
                'estado' => EstadoSerie::VENDIDA,
                'observaciones' => trim(($nuevaBloqueada->observaciones ?? '')."\nEntregada por cambio en devolución {$devolucion->codigo}"),
            ]);

            // Registrar movimiento de salida para la serie nueva
            $stockActual = $this->inventarioService->stockDisponible($productoNuevo);
            MovimientoInventario::create([
                'producto_id' => $productoNuevo->id,
                'serie_id' => $nuevaBloqueada->id,
                'tipo' => TipoMovimiento::SALIDA,
                'cantidad' => -1,
                'stock_resultante' => $stockActual,
                'referencia_tipo' => Devolucion::class,
                'referencia_id' => $devolucion->id,
                'motivo' => "Entrega por cambio de producto en devolución {$devolucion->codigo}",
                'user_id' => $usuario->id,
            ]);

            // 3. Registrar registro de CambioSerie
            $cambio = CambioSerie::create([
                'devolucion_id' => $devolucion->id,
                'serie_original_id' => $serieOriginal->id,
                'serie_nueva_id' => $nuevaBloqueada->id,
                'diferencia_monto' => $diferenciaMonto,
            ]);

            // 4. Generar garantía para la serie nueva
            $garantiaOriginal = Garantia::where('serie_id', $serieOriginal->id)
                ->where('estado', EstadoGarantia::VIGENTE)
                ->first();

            $politica = $this->garantiaService->resolverPolitica($productoNuevo->id, $productoNuevo->categoria_id);
            $hoy = Carbon::today();

            $heredarGarantia = filter_var(Configuracion::obtener('garantia_heredar_en_cambio', '0'), FILTER_VALIDATE_BOOLEAN);

            if ($heredarGarantia && $garantiaOriginal !== null) {
                $fechaFin = $garantiaOriginal->fecha_fin->gt($hoy) ? $garantiaOriginal->fecha_fin : (clone $hoy)->addMonths($politica->meses_garantia);
                $diasCambioHasta = $garantiaOriginal->dias_cambio_hasta->gt($hoy) ? $garantiaOriginal->dias_cambio_hasta : (clone $hoy)->addDays($politica->dias_cambio);
            } else {
                // Por defecto: reinicia plazo completo
                $fechaFin = (clone $hoy)->addMonths($politica->meses_garantia);
                $diasCambioHasta = (clone $hoy)->addDays($politica->dias_cambio);
            }

            $nuevaGarantia = Garantia::create([
                'serie_id' => $nuevaBloqueada->id,
                'venta_detalle_id' => $detalleOriginal?->venta_detalle_id ?? $venta->detalles()->first()->id,
                'cliente_id' => $devolucion->cliente_id,
                'politica_garantia_id' => $politica->id,
                'fecha_inicio' => $hoy,
                'fecha_fin' => $fechaFin,
                'dias_cambio_hasta' => $diasCambioHasta,
                'estado' => EstadoGarantia::VIGENTE,
                'condiciones_snapshot' => $politica->snapshotCondiciones()."\n[Generada por cambio de serie {$serieOriginal->numero_serie} en devolución {$devolucion->codigo}]",
            ]);

            return [
                'cambio' => $cambio,
                'garantia' => $nuevaGarantia,
                'diferencia' => $diferenciaMonto,
            ];
        });
    }
}
