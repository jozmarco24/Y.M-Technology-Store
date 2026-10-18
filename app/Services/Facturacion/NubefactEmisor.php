<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Enums\TipoComprobanteVenta;
use App\Models\ComprobanteElectronico;
use App\Models\NotaCredito;
use App\Models\Venta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NubefactEmisor implements EmisorComprobante
{
    protected string $ruta;

    protected string $token;

    protected int $timeout;

    protected int $retries;

    public function __construct(
        ?string $ruta = null,
        ?string $token = null
    ) {
        $this->ruta = $ruta ?? (string) (config('facturacion.ruta') ?: config('facturacion.nubefact.ruta'));
        $this->token = $token ?? (string) (config('facturacion.token') ?: config('facturacion.nubefact.token'));
        $this->timeout = (int) (config('facturacion.timeout') ?: config('facturacion.nubefact.timeout', 15));
        $this->retries = (int) (config('facturacion.retries') ?: config('facturacion.nubefact.retries', 2));
    }

    public function emitir(Venta $venta): ResultadoEmision
    {
        $payload = app(FacturacionService::class)->construirPayload($venta);

        return $this->enviarSolicitud($payload);
    }

    public function consultar(ComprobanteElectronico $comprobante): ResultadoEmision
    {
        $tipoDoc = $comprobante->tipo === 'factura' ? 1 : 2;

        $payload = [
            'operacion' => 'consultar_comprobante',
            'tipo_de_comprobante' => $tipoDoc,
            'serie' => $comprobante->serie,
            'numero' => $comprobante->numero,
        ];

        return $this->enviarSolicitud($payload);
    }

    public function anular(ComprobanteElectronico $comprobante, string $motivo): ResultadoEmision
    {
        $tipoDoc = $comprobante->tipo === 'factura' ? 1 : 2;

        $payload = [
            'operacion' => 'generar_anulacion',
            'tipo_de_comprobante' => $tipoDoc,
            'serie' => $comprobante->serie,
            'numero' => $comprobante->numero,
            'motivo' => $motivo,
            'codigo_unico' => "ANUL-{$comprobante->serie}-{$comprobante->numero}",
        ];

        return $this->enviarSolicitud($payload);
    }

    public function emitirNotaCredito(NotaCredito $notaCredito): ResultadoEmision
    {
        $venta = $notaCredito->venta;
        $esFactura = $venta->tipo_comprobante === TipoComprobanteVenta::FACTURA;

        $payload = [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => 3, // 3 = Nota de Crédito
            'serie' => $notaCredito->serie,
            'numero' => $notaCredito->numero,
            'cliente_tipo_de_documento' => $esFactura ? 6 : 1,
            'cliente_numero_de_documento' => $venta->cliente?->numero_documento ?? '00000000',
            'cliente_denominacion' => $venta->cliente?->nombre_razon_social ?? 'CLIENTES VARIOS',
            'cliente_direccion' => $venta->cliente?->direccion ?? '',
            'fecha_de_emision' => now()->format('d-m-Y'),
            'moneda' => 1, // PEN
            'tipo_de_nota_de_credito' => (int) $notaCredito->motivo_sunat,
            'documento_que_se_modifica_tipo' => $esFactura ? 1 : 2,
            'documento_que_se_modifica_serie' => $venta->serie_comprobante,
            'documento_que_se_modifica_numero' => $venta->numero_comprobante,
            'total' => (float) $notaCredito->monto,
            'total_igv' => round(((float) $notaCredito->monto / 1.18) * 0.18, 2),
            'total_gravada' => round((float) $notaCredito->monto / 1.18, 2),
            'items' => [
                [
                    'unidad_de_medida' => 'NIU',
                    'codigo' => 'NC-DEV',
                    'descripcion' => 'DEVOLUCIÓN DE MERCADERÍA / COMPROBANTE DE VENTA '.$venta->codigo,
                    'cantidad' => 1,
                    'valor_unitario' => round((float) $notaCredito->monto / 1.18, 2),
                    'precio_unitario' => (float) $notaCredito->monto,
                    'subtotal' => round((float) $notaCredito->monto / 1.18, 2),
                    'tipo_de_igv' => 1, // Gravado - Operación Onerosa
                    'igv' => round(((float) $notaCredito->monto / 1.18) * 0.18, 2),
                    'total' => (float) $notaCredito->monto,
                ],
            ],
        ];

        return $this->enviarSolicitud($payload);
    }

    /**
     * Envío HTTP con timeout, reintentos y clasificación de errores.
     * NUNCA registra el token en los logs.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function enviarSolicitud(array $payload): ResultadoEmision
    {
        if (empty($this->ruta) || empty($this->token)) {
            return ResultadoEmision::errorRecuperable(
                'Las credenciales de Nubefact (ruta o token) no están configuradas.',
                $payload
            );
        }

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 500, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->ruta, $payload);

            $status = $response->status();
            $data = $response->json();

            if (! is_array($data)) {
                $rawBody = substr($response->body(), 0, 500);

                return ResultadoEmision::errorRecuperable(
                    "Respuesta no válida del servidor Nubefact (HTTP {$status}): {$rawBody}",
                    $payload
                );
            }

            // Caso 1: Error 5xx o timeout del servidor de Nubefact (Recuperable)
            if ($status >= 500) {
                $mensajeError = $data['errors'] ?? 'Error interno del servidor Nubefact (5xx)';

                return ResultadoEmision::errorRecuperable(
                    "Error de servidor en Nubefact (HTTP {$status}): {$mensajeError}",
                    $payload,
                    $data
                );
            }

            // Caso 2: Error 4xx (Errores de validación, RUC no existe, etc.)
            if ($response->failed() || ! empty($data['errors'])) {
                $mensajeError = is_string($data['errors'] ?? null)
                    ? $data['errors']
                    : json_encode($data['errors'] ?? 'Error de validación');

                // Si Nubefact responde que el comprobante ya existe, es idempotente: consultar
                if (str_contains(strtolower((string) $mensajeError), 'ya fue emitido') || str_contains(strtolower((string) $mensajeError), 'ya existe')) {
                    return $this->tratarComoYaExistente($payload, $data);
                }

                $codigo = (string) ($data['codigo'] ?? $status);

                return ResultadoEmision::rechazado(
                    mensaje: "Rechazado por SUNAT / Nubefact: {$mensajeError}",
                    codigoSunat: $codigo,
                    payload: $payload,
                    respuesta: $data
                );
            }

            // Caso 3: Éxito (HTTP 200 con respuesta válida)
            $pdf = (string) ($data['enlace_del_pdf'] ?? $data['enlace'] ?? '');
            $xml = (string) ($data['enlace_del_xml'] ?? '');
            $cdr = (string) ($data['enlace_del_cdr'] ?? '');
            $qr = (string) ($data['cadena_para_codigo_qr'] ?? '');
            $hash = (string) ($data['codigo_hash'] ?? '');
            $mensaje = (string) ($data['sunat_description'] ?? 'Comprobante aceptado exitosamente por SUNAT.');

            return ResultadoEmision::aceptado(
                enlacePdf: $pdf,
                enlaceXml: $xml,
                enlaceCdr: $cdr,
                codigoQr: $qr,
                hash: $hash,
                payload: $payload,
                respuesta: $data,
                mensaje: $mensaje
            );
        } catch (ConnectionException $e) {
            // Error de conexión o timeout de red (Recuperable)
            Log::warning('Conexión fallida al emitir comprobante con Nubefact', [
                'operacion' => $payload['operacion'] ?? 'generar_comprobante',
                'error' => $e->getMessage(),
            ]);

            return ResultadoEmision::errorRecuperable(
                'Tiempo de espera agotado o error de conexión con Nubefact: '.$e->getMessage(),
                $payload
            );
        } catch (\Throwable $e) {
            Log::error('Excepción al conectar con Nubefact', [
                'error' => $e->getMessage(),
            ]);

            return ResultadoEmision::errorRecuperable(
                'Error inesperado al emitir comprobante: '.$e->getMessage(),
                $payload
            );
        }
    }

    /**
     * Maneja el caso en que el comprobante ya fue emitido previamente en Nubefact.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $errorData
     */
    protected function tratarComoYaExistente(array $payload, array $errorData): ResultadoEmision
    {
        $tipo = (int) ($payload['tipo_de_comprobante'] ?? 2);
        $serie = (string) ($payload['serie'] ?? '');
        $numero = (int) ($payload['numero'] ?? 0);

        // Consultamos a Nubefact para recuperar el PDF, XML y CDR generados
        $consulta = [
            'operacion' => 'consultar_comprobante',
            'tipo_de_comprobante' => $tipo,
            'serie' => $serie,
            'numero' => $numero,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->ruta, $consulta);

            $data = $response->json();
            if (is_array($data) && ! empty($data['enlace_del_pdf'])) {
                return ResultadoEmision::aceptado(
                    enlacePdf: (string) $data['enlace_del_pdf'],
                    enlaceXml: (string) ($data['enlace_del_xml'] ?? ''),
                    enlaceCdr: (string) ($data['enlace_del_cdr'] ?? ''),
                    codigoQr: (string) ($data['cadena_para_codigo_qr'] ?? ''),
                    hash: (string) ($data['codigo_hash'] ?? ''),
                    payload: $payload,
                    respuesta: $data,
                    mensaje: 'Comprobante previamente aceptado sincronizado con éxito.'
                );
            }
        } catch (\Throwable $e) {
            // Ignoramos y dejamos el rechazo si no se pudo consultar
        }

        return ResultadoEmision::rechazado(
            mensaje: 'El comprobante ya fue emitido previamente pero no se pudo sincronizar.',
            codigoSunat: 'DUPLICADO',
            payload: $payload,
            respuesta: $errorData
        );
    }
}
