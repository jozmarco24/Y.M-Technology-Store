<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Enums\EstadoComprobante;

class ResultadoEmision
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $respuesta
     */
    public function __construct(
        public bool $exito,
        public EstadoComprobante $estado,
        public ?string $mensaje = null,
        public ?string $codigoSunat = null,
        public ?string $enlacePdf = null,
        public ?string $enlaceXml = null,
        public ?string $enlaceCdr = null,
        public ?string $codigoQr = null,
        public ?string $hash = null,
        public array $payload = [],
        public array $respuesta = [],
        public bool $esRecuperable = false,
        public ?string $ticketAnulacion = null
    ) {}

    public static function aceptado(
        string $enlacePdf,
        string $enlaceXml,
        string $enlaceCdr,
        string $codigoQr,
        string $hash,
        array $payload,
        array $respuesta,
        string $mensaje = 'Aceptado por SUNAT'
    ): self {
        return new self(
            exito: true,
            estado: EstadoComprobante::ACEPTADO,
            mensaje: $mensaje,
            codigoSunat: '0',
            enlacePdf: $enlacePdf,
            enlaceXml: $enlaceXml,
            enlaceCdr: $enlaceCdr,
            codigoQr: $codigoQr,
            hash: $hash,
            payload: $payload,
            respuesta: $respuesta,
            esRecuperable: false
        );
    }

    public static function rechazado(
        string $mensaje,
        ?string $codigoSunat,
        array $payload,
        array $respuesta
    ): self {
        return new self(
            exito: false,
            estado: EstadoComprobante::RECHAZADO,
            mensaje: $mensaje,
            codigoSunat: $codigoSunat,
            payload: $payload,
            respuesta: $respuesta,
            esRecuperable: false
        );
    }

    public static function errorRecuperable(
        string $mensaje,
        array $payload = [],
        array $respuesta = []
    ): self {
        return new self(
            exito: false,
            estado: EstadoComprobante::ERROR,
            mensaje: $mensaje,
            payload: $payload,
            respuesta: $respuesta,
            esRecuperable: true
        );
    }
}
