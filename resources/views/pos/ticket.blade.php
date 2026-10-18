<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket de Venta - {{ $venta->codigo }}</title>
    <style>
        @page {
            margin: 0;
            size: 80mm auto;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.25;
            color: #000;
            background: #fff;
            width: 76mm;
            margin: 2mm auto;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .double-divider {
            border-top: 1px double #000;
            margin: 4px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 2px 0;
        }
        .qr-placeholder {
            width: 110px;
            height: 110px;
            margin: 8px auto;
            border: 1px dashed #555;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #555;
            text-align: center;
        }
        @media print {
            body { width: 100%; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 10px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 12px; font-weight: bold; cursor: pointer;">Imprimir Ticket</button>
        <button onclick="window.close()" style="padding: 6px 12px; margin-left: 8px; cursor: pointer;">Cerrar</button>
    </div>

    <!-- CABECERA DE LA TIENDA -->
    <div class="text-center">
        <div class="bold" style="font-size: 14px;">{{ $config['nombre'] }}</div>
        <div>{{ $config['razon_social'] }}</div>
        <div class="bold">RUC: {{ $config['ruc'] }}</div>
        <div>{{ $config['direccion'] }}</div>
        @if (!empty($config['telefono']))
            <div>Telf: {{ $config['telefono'] }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- TIPO Y CORRELATIVO DE COMPROBANTE -->
    <div class="text-center bold" style="font-size: 14px; margin: 4px 0;">
        {{ strtoupper($venta->tipo_comprobante->etiqueta()) }} ELECTRÓNICA
    </div>
    <div class="text-center bold" style="font-size: 13px;">
        {{ $venta->serie_comprobante }} - {{ str_pad((string)$venta->numero_comprobante, 8, '0', STR_PAD_LEFT) }}
    </div>

    <div class="divider"></div>

    <!-- DATOS DE OPERACIÓN -->
    <table>
        <tr>
            <td class="bold">Fecha/Hora:</td>
            <td class="text-right">{{ $venta->created_at->format('d/m/Y H:i:s') }}</td>
        </tr>
        <tr>
            <td class="bold">Vendedor:</td>
            <td class="text-right">{{ $venta->vendedor->name }}</td>
        </tr>
        <tr>
            <td class="bold">Cliente:</td>
            <td class="text-right">{{ $venta->cliente->nombre_razon_social }}</td>
        </tr>
        <tr>
            <td class="bold">{{ $venta->cliente->tipo_documento->value }}:</td>
            <td class="text-right">{{ $venta->cliente->numero_documento ?? 'SIN DOCUMENTO' }}</td>
        </tr>
        @if ($venta->cliente->direccion)
            <tr>
                <td class="bold">Dirección:</td>
                <td class="text-right">{{ $venta->cliente->direccion }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <!-- DETALLE DE ÍTEMS -->
    <table>
        <thead>
            <tr>
                <th class="text-left" style="width: 15%;">Cant</th>
                <th class="text-left" style="width: 55%;">Descripción</th>
                <th class="text-right" style="width: 30%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                <tr>
                    <td class="text-left" style="vertical-align: top;">{{ $detalle->cantidad }}</td>
                    <td class="text-left">
                        {{ $detalle->producto->nombre }}
                        @if ($detalle->serie)
                            <br><span style="font-size: 10px;">SN: {{ $detalle->serie->numero_serie }}</span>
                        @endif
                        @if ($detalle->descuento > 0)
                            <br><span style="font-size: 10px;">(Dscto: S/ {{ number_format((float)$detalle->descuento, 2) }})</span>
                        @endif
                    </td>
                    <td class="text-right" style="vertical-align: top;">S/ {{ number_format((float)$detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- TOTALES -->
    <table>
        <tr>
            <td>SUBTOTAL:</td>
            <td class="text-right">S/ {{ number_format((float)$venta->subtotal, 2) }}</td>
        </tr>
        @if ($venta->descuento > 0)
            <tr>
                <td>DESCUENTO GLOBAL:</td>
                <td class="text-right">- S/ {{ number_format((float)$venta->descuento, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>I.G.V. (18%):</td>
            <td class="text-right">S/ {{ number_format((float)$venta->igv, 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 14px;">
            <td>TOTAL A PAGAR:</td>
            <td class="text-right">S/ {{ number_format((float)$venta->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- FORMAS DE PAGO -->
    <table>
        <tr class="bold"><td colspan="2">FORMAS DE PAGO:</td></tr>
        @php
            $totalPagadoNeto = 0.0;
        @endphp
        @foreach ($venta->pagos as $pago)
            @php $totalPagadoNeto += (float)$pago->monto; @endphp
            <tr>
                <td>{{ $pago->metodo->etiqueta() }}{{ $pago->referencia ? ' ('.$pago->referencia.')' : '' }}:</td>
                <td class="text-right">S/ {{ number_format((float)$pago->monto, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <!-- SECCIÓN SUNAT (CÓDIGO QR, HASH O ESTADO PENDIENTE) -->
    @if ($venta->tipo_comprobante->value !== 'nota_venta')
        @php
            $comprobante = $venta->comprobanteElectronico;
        @endphp
        @if ($comprobante && $comprobante->estaAceptado())
            <div class="text-center" style="margin: 10px 0;">
                @if ($comprobante->codigo_qr)
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($comprobante->codigo_qr) }}" 
                         alt="QR SUNAT" 
                         style="width: 130px; height: 130px; margin: 0 auto; display: block;" />
                @endif
                @if ($comprobante->hash)
                    <div style="font-size: 9px; color: #333; margin-top: 4px; word-break: break-all;">
                        Hash: {{ $comprobante->hash }}
                    </div>
                @endif
                <div style="font-size: 9px; color: #555; margin-top: 2px;">
                    Representación impresa de la {{ $venta->tipo_comprobante->etiqueta() }} Electrónica
                </div>
            </div>
        @else
            <div class="text-center" style="margin: 10px 0; border: 1px dashed #666; padding: 6px; font-size: 11px;">
                <strong>Comprobante en proceso de envío a SUNAT</strong><br>
                <span style="font-size: 9px; color: #666;">Estado actual: {{ $comprobante ? $comprobante->estado->value : 'pendiente' }}</span>
            </div>
        @endif
    @else
        <div class="text-center" style="margin: 6px 0; font-size: 10px; color: #666;">
            DOCUMENTO INTERNO - NO VÁLIDO PARA CRÉDITO FISCAL
        </div>
    @endif

    <div class="divider"></div>

    <!-- PIE DE TICKET -->
    <div class="text-center" style="font-size: 11px; margin-top: 6px;">
        {{ $config['mensaje_pie'] }}
        <div style="font-size: 9px; margin-top: 4px; color: #666;">
            Sistema POS SYS-COPM v2.0
        </div>
    </div>
</body>
</html>
