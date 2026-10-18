<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Enums\EstadoComprobante;
use App\Models\ComprobanteElectronico;
use App\Models\NotaCredito;
use App\Models\Venta;

class EmisorSimulado implements EmisorComprobante
{
    public function emitir(Venta $venta): ResultadoEmision
    {
        $payload = app(FacturacionService::class)->construirPayload($venta);

        $serie = $venta->serie_comprobante;
        $numero = str_pad((string) $venta->numero_comprobante, 8, '0', STR_PAD_LEFT);

        $respuestaSimulada = [
            'tipo_de_comprobante' => $payload['tipo_de_comprobante'],
            'serie' => $serie,
            'numero' => $numero,
            'enlace' => "https://facturacion.ejemplo.pe/comprobantes/{$serie}-{$numero}",
            'enlace_del_pdf' => "https://facturacion.ejemplo.pe/pdf/{$serie}-{$numero}.pdf",
            'enlace_del_xml' => "https://facturacion.ejemplo.pe/xml/{$serie}-{$numero}.xml",
            'enlace_del_cdr' => "https://facturacion.ejemplo.pe/cdr/{$serie}-{$numero}.zip",
            'cadena_para_codigo_qr' => "20601234568|{$payload['tipo_de_comprobante']}|{$serie}|{$numero}|{$payload['total_igv']}|{$payload['total']}|".now()->format('Y-m-d')."|{$payload['cliente_tipo_de_documento']}|{$payload['cliente_numero_de_documento']}|",
            'codigo_hash' => hash('sha256', "{$serie}-{$numero}-{$payload['total']}"),
            'sunat_description' => 'La Boleta o Factura número '.$serie.'-'.$numero.', ha sido aceptada.',
            'sunat_responsecode' => '0',
            'sunat_soap_error' => '',
        ];

        return ResultadoEmision::aceptado(
            enlacePdf: $respuestaSimulada['enlace_del_pdf'],
            enlaceXml: $respuestaSimulada['enlace_del_xml'],
            enlaceCdr: $respuestaSimulada['enlace_del_cdr'],
            codigoQr: $respuestaSimulada['cadena_para_codigo_qr'],
            hash: $respuestaSimulada['codigo_hash'],
            payload: $payload,
            respuesta: $respuestaSimulada,
            mensaje: $respuestaSimulada['sunat_description']
        );
    }

    public function consultar(ComprobanteElectronico $comprobante): ResultadoEmision
    {
        $payload = [
            'operacion' => 'consultar_comprobante',
            'tipo_de_comprobante' => $comprobante->tipo === 'factura' ? 1 : 2,
            'serie' => $comprobante->serie,
            'numero' => $comprobante->numero,
        ];

        $serie = $comprobante->serie;
        $numero = str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT);

        $respuesta = [
            'enlace_del_pdf' => "https://facturacion.ejemplo.pe/pdf/{$serie}-{$numero}.pdf",
            'enlace_del_xml' => "https://facturacion.ejemplo.pe/xml/{$serie}-{$numero}.xml",
            'enlace_del_cdr' => "https://facturacion.ejemplo.pe/cdr/{$serie}-{$numero}.zip",
            'cadena_para_codigo_qr' => "20601234568|{$serie}|{$numero}|QR-SIMULADO|",
            'codigo_hash' => hash('sha256', "{$serie}-{$numero}"),
            'sunat_description' => 'Comprobante aceptado en consulta',
            'sunat_responsecode' => '0',
        ];

        return ResultadoEmision::aceptado(
            enlacePdf: $respuesta['enlace_del_pdf'],
            enlaceXml: $respuesta['enlace_del_xml'],
            enlaceCdr: $respuesta['enlace_del_cdr'],
            codigoQr: $respuesta['cadena_para_codigo_qr'],
            hash: $respuesta['codigo_hash'],
            payload: $payload,
            respuesta: $respuesta,
            mensaje: $respuesta['sunat_description']
        );
    }

    public function anular(ComprobanteElectronico $comprobante, string $motivo): ResultadoEmision
    {
        $payload = [
            'operacion' => 'generar_anulacion',
            'tipo_de_comprobante' => $comprobante->tipo === 'factura' ? 1 : 2,
            'serie' => $comprobante->serie,
            'numero' => $comprobante->numero,
            'motivo' => $motivo,
        ];

        $respuesta = [
            'sunat_description' => 'El comprobante ha sido anulado con éxito en SUNAT.',
            'sunat_responsecode' => '0',
            'ticket' => 'TICKET-SIMULADO-'.time(),
        ];

        return new ResultadoEmision(
            exito: true,
            estado: EstadoComprobante::ACEPTADO,
            mensaje: $respuesta['sunat_description'],
            codigoSunat: '0',
            payload: $payload,
            respuesta: $respuesta,
            ticketAnulacion: $respuesta['ticket']
        );
    }

    public function emitirNotaCredito(NotaCredito $notaCredito): ResultadoEmision
    {
        $payload = [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => 3, // 3 = Nota de Crédito
            'serie' => $notaCredito->serie,
            'numero' => $notaCredito->numero,
            'total' => (float) $notaCredito->monto,
            'motivo' => $notaCredito->motivo_sunat,
        ];

        $serie = $notaCredito->serie;
        $numero = str_pad((string) $notaCredito->numero, 8, '0', STR_PAD_LEFT);

        $respuesta = [
            'enlace_del_pdf' => "https://facturacion.ejemplo.pe/pdf/{$serie}-{$numero}.pdf",
            'enlace_del_xml' => "https://facturacion.ejemplo.pe/xml/{$serie}-{$numero}.xml",
            'enlace_del_cdr' => "https://facturacion.ejemplo.pe/cdr/{$serie}-{$numero}.zip",
            'cadena_para_codigo_qr' => "20601234568|{$serie}|{$numero}|{$notaCredito->monto}|QR-NC-SIMULADO|",
            'codigo_hash' => hash('sha256', "NC-{$serie}-{$numero}"),
            'sunat_description' => 'La Nota de Crédito ha sido aceptada en SUNAT.',
            'sunat_responsecode' => '0',
        ];

        return ResultadoEmision::aceptado(
            enlacePdf: $respuesta['enlace_del_pdf'],
            enlaceXml: $respuesta['enlace_del_xml'],
            enlaceCdr: $respuesta['enlace_del_cdr'],
            codigoQr: $respuesta['cadena_para_codigo_qr'],
            hash: $respuesta['codigo_hash'],
            payload: $payload,
            respuesta: $respuesta,
            mensaje: $respuesta['sunat_description']
        );
    }
}
