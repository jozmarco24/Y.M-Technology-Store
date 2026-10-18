<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoGarantia;
use App\Models\Garantia;
use App\Models\PoliticaGarantia;
use App\Models\Serie;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GarantiaService
{
    /**
     * Resuelve la política más específica aplicable:
     * 1. Específica por Producto.
     * 2. Si no, específica por Categoría del producto.
     * 3. Si no, la Política General (categoria_id is null and producto_id is null).
     */
    public function resolverPolitica(int $productoId, ?int $categoriaId = null): PoliticaGarantia
    {
        // 1. Específica por producto
        $politicaProducto = PoliticaGarantia::activas()
            ->where('producto_id', $productoId)
            ->first();

        if ($politicaProducto !== null) {
            return $politicaProducto;
        }

        // 2. Específica por categoría
        if ($categoriaId !== null) {
            $politicaCategoria = PoliticaGarantia::activas()
                ->where('categoria_id', $categoriaId)
                ->whereNull('producto_id')
                ->first();

            if ($politicaCategoria !== null) {
                return $politicaCategoria;
            }
        }

        // 3. Política general
        $politicaGeneral = PoliticaGarantia::activas()
            ->whereNull('producto_id')
            ->whereNull('categoria_id')
            ->first();

        if ($politicaGeneral !== null) {
            return $politicaGeneral;
        }

        // Si por alguna razón no existe, fallback creado en caliente
        return PoliticaGarantia::create([
            'nombre' => 'Garantía Estándar',
            'meses_garantia' => 12,
            'dias_cambio' => 7,
            'cubre' => 'Fallas de fabricación.',
            'no_cubre' => 'Daño físico o mal uso.',
            'activo' => true,
        ]);
    }

    /**
     * Generar garantías para todas las series vendidas de una venta.
     * Se ejecuta dentro de la misma transacción de VentaService.
     *
     * @return Collection<int, Garantia>
     */
    public function generarParaVenta(Venta $venta): Collection
    {
        $garantiasCreadas = collect();
        $fechaVenta = $venta->created_at ? Carbon::parse($venta->created_at)->startOfDay() : Carbon::today();

        $venta->loadMissing(['detalles.producto', 'detalles.serie']);

        foreach ($venta->detalles as $detalle) {
            if ($detalle->serie_id === null || $detalle->serie === null) {
                continue;
            }

            $producto = $detalle->producto;
            $serie = $detalle->serie;

            $politica = $this->resolverPolitica($producto->id, $producto->categoria_id);

            $fechaFin = (clone $fechaVenta)->addMonths($politica->meses_garantia);
            $diasCambioHasta = (clone $fechaVenta)->addDays($politica->dias_cambio);

            $garantia = Garantia::create([
                'serie_id' => $serie->id,
                'venta_detalle_id' => $detalle->id,
                'cliente_id' => $venta->cliente_id,
                'politica_garantia_id' => $politica->id,
                'fecha_inicio' => $fechaVenta,
                'fecha_fin' => $fechaFin,
                'dias_cambio_hasta' => $diasCambioHasta,
                'estado' => EstadoGarantia::VIGENTE,
                'condiciones_snapshot' => $politica->snapshotCondiciones(),
            ]);

            $garantiasCreadas->push($garantia);
        }

        return $garantiasCreadas;
    }

    /**
     * Consultar garantía por número de serie.
     *
     * @return array{
     *     encontrado: bool,
     *     serie?: string,
     *     producto?: string,
     *     cliente?: string,
     *     venta_codigo?: string,
     *     fecha_venta?: string,
     *     estado?: string,
     *     vigente?: bool,
     *     dias_restantes?: int,
     *     fecha_inicio?: string,
     *     fecha_fin?: string,
     *     dias_cambio_hasta?: string,
     *     en_plazo_cambio?: bool,
     *     condiciones?: string|null,
     *     garantia_id?: int
     * }
     */
    public function consultarPorSerie(string $numero): array
    {
        $numeroLimpio = trim(strtoupper($numero));
        $serie = Serie::where('numero_serie', $numeroLimpio)->first();

        if (! $serie) {
            return ['encontrado' => false];
        }

        $garantia = Garantia::where('serie_id', $serie->id)
            ->with(['ventaDetalle.venta.cliente', 'ventaDetalle.producto', 'cliente'])
            ->latest('id')
            ->first();

        if (! $garantia) {
            return [
                'encontrado' => true,
                'serie' => $serie->numero_serie,
                'producto' => $serie->producto?->nombre ?? '',
                'estado' => 'sin_garantia',
                'vigente' => false,
                'dias_restantes' => 0,
            ];
        }

        $venta = $garantia->ventaDetalle?->venta;
        $producto = $garantia->ventaDetalle?->producto ?? $serie->producto;
        $cliente = $garantia->cliente ?? $venta?->cliente;

        return [
            'encontrado' => true,
            'garantia_id' => $garantia->id,
            'serie' => $serie->numero_serie,
            'producto' => $producto?->nombre ?? '',
            'cliente' => $cliente?->nombre_razon_social ?? '',
            'venta_codigo' => $venta?->codigo ?? '',
            'fecha_venta' => $garantia->fecha_inicio->format('d/m/Y'),
            'estado' => $garantia->estado->value,
            'vigente' => $garantia->esVigente(),
            'dias_restantes' => $garantia->diasRestantes(),
            'fecha_inicio' => $garantia->fecha_inicio->format('d/m/Y'),
            'fecha_fin' => $garantia->fecha_fin->format('d/m/Y'),
            'dias_cambio_hasta' => $garantia->dias_cambio_hasta->format('d/m/Y'),
            'en_plazo_cambio' => $garantia->estaEnPlazoCambio(),
            'condiciones' => $garantia->condiciones_snapshot,
        ];
    }

    /**
     * Verifica si una serie tiene garantía vigente.
     */
    public function estaVigente(Serie $serie): bool
    {
        $garantia = Garantia::where('serie_id', $serie->id)
            ->where('estado', EstadoGarantia::VIGENTE)
            ->latest('id')
            ->first();

        return $garantia !== null && $garantia->esVigente();
    }

    /**
     * Días restantes de garantía para una serie.
     */
    public function diasRestantes(Serie $serie): int
    {
        $garantia = Garantia::where('serie_id', $serie->id)
            ->where('estado', EstadoGarantia::VIGENTE)
            ->latest('id')
            ->first();

        return $garantia ? $garantia->diasRestantes() : 0;
    }

    /**
     * Proceso masivo para marcar como vencidas las garantías expiradas.
     * Utilizado por el scheduler diario.
     */
    public function marcarVencidas(): int
    {
        return Garantia::where('estado', EstadoGarantia::VIGENTE)
            ->whereDate('fecha_fin', '<', Carbon::today())
            ->update(['estado' => EstadoGarantia::VENCIDA]);
    }

    /**
     * Anula las garantías vigentes asociadas a una venta anulada.
     */
    public function anularPorAnulacionDeVenta(Venta $venta): int
    {
        $detallesIds = $venta->detalles()->pluck('id');

        return Garantia::whereIn('venta_detalle_id', $detallesIds)
            ->where('estado', EstadoGarantia::VIGENTE)
            ->update(['estado' => EstadoGarantia::ANULADA]);
    }
}
