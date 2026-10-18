<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Enums\EstadoComprobante;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Models\ComprobanteElectronico;
use App\Models\User;
use App\Models\Venta;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class FacturacionService
{
    public function __construct(
        protected EmisorComprobante $emisor
    ) {}

    /**
     * Construir el payload JSON compatible con Nubefact.
     * Incluye datos de cabecera, cliente, y número de serie en la descripción de cada ítem con serie.
     *
     * @return array<string, mixed>
     */
    public function construirPayload(Venta $venta): array
    {
        $venta->loadMissing(['cliente', 'detalles.producto', 'detalles.serie']);

        $tipoNubefact = match ($venta->tipo_comprobante) {
            TipoComprobanteVenta::FACTURA => 1,
            TipoComprobanteVenta::BOLETA => 2,
            default => throw new InvalidArgumentException("El tipo de comprobante '{$venta->tipo_comprobante->value}' no es electrónico."),
        };

        $cliente = $venta->cliente;
        $tipoDocCliente = match ($cliente->tipo_documento) {
            TipoDocumentoCliente::RUC => '6',
            TipoDocumentoCliente::DNI => '1',
            TipoDocumentoCliente::CE => '4',
            default => '-',
        };

        $itemsPayload = [];
        foreach ($venta->detalles as $detalle) {
            $descripcion = $detalle->descripcion;
            if ($detalle->serie !== null && ! str_contains($descripcion, $detalle->serie->numero_serie)) {
                $descripcion .= " - SN: {$detalle->serie->numero_serie}";
            }

            // Los precios en el sistema son finales (incluyen IGV 18%)
            $precioUnitarioConIgv = (float) $detalle->precio_unitario - (float) $detalle->descuento;
            $valorUnitarioSinIgv = round($precioUnitarioConIgv / 1.18, 2);
            $subtotalItem = round($valorUnitarioSinIgv * $detalle->cantidad, 2);
            $totalItem = round($precioUnitarioConIgv * $detalle->cantidad, 2);
            $igvItem = round($totalItem - $subtotalItem, 2);

            $itemsPayload[] = [
                'unidad_de_medida' => 'NIU',
                'codigo' => $detalle->producto->sku,
                'descripcion' => $descripcion,
                'cantidad' => $detalle->cantidad,
                'valor_unitario' => $valorUnitarioSinIgv,
                'precio_unitario' => $precioUnitarioConIgv,
                'descuento' => '',
                'subtotal' => $subtotalItem,
                'tipo_de_igv' => 1, // Gravado - Operación Onerosa
                'igv' => $igvItem,
                'total' => $totalItem,
                'anticipo_regularizacion' => false,
                'anticipo_comprobante_serie' => '',
                'anticipo_comprobante_numero' => '',
            ];
        }

        $fechaEmision = $venta->created_at ? $venta->created_at->format('d-m-Y') : now()->format('d-m-Y');

        return [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => $tipoNubefact,
            'serie' => $venta->serie_comprobante,
            'numero' => $venta->numero_comprobante,
            'sunat_transaction' => 1,
            'cliente_tipo_de_documento' => $tipoDocCliente,
            'cliente_numero_de_documento' => $cliente->numero_documento ?? '-',
            'cliente_denominacion' => $cliente->nombre_razon_social,
            'cliente_direccion' => $cliente->direccion ?? '',
            'cliente_email' => $cliente->email ?? '',
            'fecha_de_emision' => $fechaEmision,
            'moneda' => 1, // Soles (PEN)
            'tipo_de_cambio' => '',
            'porcentaje_de_igv' => 18.00,
            'descuento_global' => (float) $venta->descuento,
            'total_descuento' => (float) $venta->descuento,
            'total_anticipo' => '',
            'total_gravada' => (float) $venta->subtotal,
            'total_inafecta' => '',
            'total_exonerada' => '',
            'total_igv' => (float) $venta->igv,
            'total_gratuita' => '',
            'total_otros_cargos' => '',
            'total' => (float) $venta->total,
            'detraccion' => false,
            'observaciones' => $venta->observaciones ?? '',
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'codigo_unico' => $venta->codigo,
            'condiciones_de_pago' => 'Contado',
            'medio_de_pago' => 'contado',
            'items' => $itemsPayload,
        ];
    }

    /**
     * Crear registro ComprobanteElectronico en estado PENDIENTE para una venta electrónica.
     * Las notas de venta no generan comprobante electrónico.
     */
    public function inicializarComprobante(Venta $venta): ?ComprobanteElectronico
    {
        if ($venta->tipo_comprobante === TipoComprobanteVenta::NOTA_VENTA) {
            return null;
        }

        return ComprobanteElectronico::firstOrCreate(
            ['venta_id' => $venta->id],
            [
                'tipo' => $venta->tipo_comprobante->value,
                'serie' => $venta->serie_comprobante,
                'numero' => $venta->numero_comprobante,
                'estado' => EstadoComprobante::PENDIENTE,
                'intentos' => 0,
            ]
        );
    }

    /**
     * Emitir comprobante electrónico llamando al emisor configurado.
     * Idempotente: Si ya está aceptado, no se reenvía.
     */
    public function emitir(Venta $venta): ComprobanteElectronico
    {
        if ($venta->tipo_comprobante === TipoComprobanteVenta::NOTA_VENTA) {
            throw new InvalidArgumentException('Las notas de venta no se emiten a SUNAT.');
        }

        $comprobante = $this->inicializarComprobante($venta);
        if (! $comprobante) {
            throw new RuntimeException("No se pudo inicializar el comprobante para la venta {$venta->codigo}.");
        }

        // Idempotencia: si ya está aceptado, no reenviar
        if ($comprobante->esAceptado()) {
            return $comprobante;
        }

        // Marcar intento
        $comprobante->update([
            'estado' => EstadoComprobante::ENVIANDO,
            'intentos' => $comprobante->intentos + 1,
            'ultimo_intento_en' => now(),
        ]);

        $resultado = $this->emisor->emitir($venta);

        $comprobante->update([
            'estado' => $resultado->estado,
            'payload_json' => $resultado->payload,
            'respuesta_json' => $resultado->respuesta,
            'enlace_pdf' => $resultado->enlacePdf ?? $comprobante->enlace_pdf,
            'enlace_xml' => $resultado->enlaceXml ?? $comprobante->enlace_xml,
            'enlace_cdr' => $resultado->enlaceCdr ?? $comprobante->enlace_cdr,
            'codigo_qr' => $resultado->codigoQr ?? $comprobante->codigo_qr,
            'hash' => $resultado->hash ?? $comprobante->hash,
            'codigo_sunat' => $resultado->codigoSunat,
            'mensaje_sunat' => $resultado->mensaje,
            'aceptado_en' => $resultado->exito ? now() : $comprobante->aceptado_en,
        ]);

        if ($resultado->estado === EstadoComprobante::RECHAZADO) {
            Log::warning("Comprobante {$comprobante->serie}-{$comprobante->numero} de venta {$venta->codigo} fue RECHAZADO por SUNAT: {$resultado->mensaje}");
            if (Schema::hasTable('notifications')) {
                try {
                    $admins = User::role('admin')->get();
                    Notification::make()
                        ->title("Comprobante {$comprobante->serie}-{$comprobante->numero} RECHAZADO por SUNAT")
                        ->body("Venta {$venta->codigo}: ".($resultado->mensaje ?? 'Rechazado por SUNAT'))
                        ->danger()
                        ->sendToDatabase($admins);
                } catch (\Throwable) {
                    // Ignorar si no se puede enviar
                }
            }
        }

        return $comprobante->fresh();
    }

    /**
     * Consultar el estado de un comprobante ante Nubefact/SUNAT.
     */
    public function consultar(ComprobanteElectronico $comprobante): ComprobanteElectronico
    {
        $resultado = $this->emisor->consultar($comprobante);

        if ($resultado->exito) {
            $comprobante->update([
                'estado' => EstadoComprobante::ACEPTADO,
                'enlace_pdf' => $resultado->enlacePdf ?? $comprobante->enlace_pdf,
                'enlace_xml' => $resultado->enlaceXml ?? $comprobante->enlace_xml,
                'enlace_cdr' => $resultado->enlaceCdr ?? $comprobante->enlace_cdr,
                'codigo_qr' => $resultado->codigoQr ?? $comprobante->codigo_qr,
                'hash' => $resultado->hash ?? $comprobante->hash,
                'mensaje_sunat' => $resultado->mensaje ?? 'Comprobante aceptado en consulta',
                'aceptado_en' => $comprobante->aceptado_en ?? now(),
            ]);
        }

        return $comprobante->fresh();
    }

    /**
     * Anular un comprobante electrónico aceptado mediante comunicación de baja / resumen.
     */
    public function anular(Venta $venta, string $motivo): ?ComprobanteElectronico
    {
        $comprobante = $venta->comprobanteElectronico;
        if (! $comprobante) {
            return null; // Si es nota de venta o no tiene comprobante electrónico, nada que anular en SUNAT
        }

        if (! $comprobante->esAceptado()) {
            // Si el comprobante aún no fue aceptado por SUNAT, se cancela localmente
            $comprobante->update([
                'estado' => EstadoComprobante::RECHAZADO,
                'mensaje_sunat' => "Cancelado por anulación de venta interna: {$motivo}",
            ]);

            return $comprobante;
        }

        $resultado = $this->emisor->anular($comprobante, $motivo);

        $comprobante->update([
            'mensaje_sunat' => $resultado->mensaje ?? 'Anulación comunicada a SUNAT.',
            'respuesta_json' => array_merge($comprobante->respuesta_json ?? [], [
                'anulacion' => $resultado->respuesta,
            ]),
        ]);

        return $comprobante->fresh();
    }
}
