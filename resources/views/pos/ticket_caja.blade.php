<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre de Caja - Sesión #{{ $sesion->id }}</title>
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
        @media print {
            body { width: 100%; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 10px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 12px; font-weight: bold; cursor: pointer;">Imprimir Reporte</button>
        <button onclick="window.close()" style="padding: 6px 12px; margin-left: 8px; cursor: pointer;">Cerrar</button>
    </div>

    <div class="text-center">
        <div class="bold" style="font-size: 14px;">{{ $config['nombre'] }}</div>
        <div>RUC: {{ $config['ruc'] }}</div>
        <div class="bold" style="font-size: 13px; margin-top: 4px;">CORTE / CIERRE DE CAJA</div>
        <div>Sesión #{{ $sesion->id }}</div>
    </div>

    <div class="divider"></div>

    <table>
        <tr>
            <td class="bold">Cajero:</td>
            <td class="text-right">{{ $sesion->usuario->name }}</td>
        </tr>
        <tr>
            <td class="bold">Apertura:</td>
            <td class="text-right">{{ $sesion->abierta_en->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="bold">Cierre:</td>
            <td class="text-right">{{ $sesion->cerrada_en ? $sesion->cerrada_en->format('d/m/Y H:i') : 'EN CURSO' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- RESUMEN DE SALDOS -->
    <table>
        <tr>
            <td>MONTO APERTURA:</td>
            <td class="text-right">S/ {{ number_format((float)$sesion->monto_apertura, 2) }}</td>
        </tr>
        <tr>
            <td>(+) INGRESOS TOTALES:</td>
            <td class="text-right">S/ {{ number_format((float)$resumen['total_ingresos'], 2) }}</td>
        </tr>
        <tr>
            <td>(-) EGRESOS TOTALES:</td>
            <td class="text-right">S/ {{ number_format((float)$resumen['total_egresos'], 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 13px;">
            <td>ESPERADO EFECTIVO:</td>
            <td class="text-right">S/ {{ number_format((float)($resumen['monto_cierre_esperado'] ?? 0), 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 13px;">
            <td>CONTADO REAL:</td>
            <td class="text-right">S/ {{ number_format((float)($resumen['monto_cierre_real'] ?? 0), 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 13px;">
            <td>DIFERENCIA:</td>
            <td class="text-right">S/ {{ number_format((float)($resumen['diferencia'] ?? 0), 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- TOTALES POR MÉTODO DE PAGO -->
    <div class="bold" style="margin-bottom: 2px;">VENTAS POR MÉTODO:</div>
    <table>
        @foreach ($resumen['por_metodo_pago'] as $metodo)
            @if ($metodo['ingresos'] > 0 || $metodo['egresos'] > 0)
                <tr>
                    <td>{{ $metodo['etiqueta'] }}:</td>
                    <td class="text-right">S/ {{ number_format((float)$metodo['neto'], 2) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    @if ($sesion->observaciones)
        <div class="divider"></div>
        <div class="bold">Observaciones:</div>
        <div>{{ $sesion->observaciones }}</div>
    @endif

    <div class="divider"></div>

    <div style="margin-top: 35px; text-align: center;">
        ___________________________<br>
        Firma Responsable Caja
    </div>
</body>
</html>
