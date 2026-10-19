<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Devolución - {{ $devolucion->codigo }}</title>
    <style>
        body { font-family: monospace; font-size: 11px; width: 72mm; margin: 0 auto; padding: 5px; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .linea { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .no-print { margin-bottom: 10px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 4px 8px; font-size: 11px; cursor: pointer;">🖨️ Imprimir Ticket</button>
    </div>

    <div class="text-center">
        <div class="bold" style="font-size: 13px;">{{ $configs['tienda_nombre'] ?? 'SYS-COPM' }}</div>
        <div>RUC: {{ $configs['tienda_ruc'] ?? '20601234568' }}</div>
        <div>{{ $configs['tienda_direccion'] ?? '' }}</div>
        <div class="linea"></div>
        <div class="bold" style="font-size: 12px;">CONSTANCIA DE DEVOLUCIÓN</div>
        <div class="bold">{{ $devolucion->codigo }}</div>
        <div>{{ $devolucion->created_at->format('d/m/Y H:i') }}</div>
        <div>Venta Ref: {{ $devolucion->venta->codigo }}</div>
    </div>

    <div class="linea"></div>
    <div><span class="bold">Cliente:</span> {{ Str::limit($devolucion->cliente->nombre_razon_social, 26) }}</div>
    <div><span class="bold">Doc:</span> {{ $devolucion->cliente->numero_documento }}</div>
    <div><span class="bold">Tipo:</span> {{ $devolucion->tipo->etiqueta() }}</div>
    <div><span class="bold">Motivo:</span> {{ $devolucion->motivo->etiqueta() }}</div>

    <div class="linea"></div>
    <table>
        @foreach($devolucion->detalles as $det)
            <tr>
                <td colspan="2" class="bold">{{ $det->producto->nombre }}</td>
            </tr>
            <tr>
                <td style="font-size: 9px;">SN: {{ $det->serie?->numero_serie ?? 'S/N' }}</td>
                <td class="text-right bold">S/ {{ number_format((float) $det->monto_devuelto, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>
    <div class="text-right bold" style="font-size: 12px;">
        TOTAL DEVUELTO: S/ {{ number_format((float) $devolucion->total_devuelto, 2) }}
    </div>

    <div style="margin-top: 35px; text-align: center;">
        ___________________________<br>
        Firma del Cliente<br>
        DNI: {{ $devolucion->cliente->numero_documento }}
    </div>
    <div class="text-center" style="font-size: 9px; margin-top: 15px;">
        SYS-COPM - Constancia de entrega/recepción
    </div>
</body>
</html>
