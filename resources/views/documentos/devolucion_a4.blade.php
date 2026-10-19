<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Devolución - {{ $devolucion->codigo }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #333; margin: 30px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px; }
        .empresa h2 { margin: 0; color: #1e3a8a; font-size: 20px; }
        .doc-box { border: 2px solid #2563eb; padding: 10px 20px; text-align: center; border-radius: 6px; }
        .doc-box h3 { margin: 0; color: #2563eb; font-size: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #1e3a8a; color: white; padding: 8px 10px; text-align: left; font-size: 12px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; font-size: 12px; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 60px; text-align: center; }
        .linea-firma { border-top: 1px solid #94a3b8; padding-top: 5px; font-weight: bold; font-size: 12px; }
        .no-print { margin-bottom: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Constancia A4
        </button>
    </div>

    <div class="header">
        <div class="empresa">
            <h2>{{ $configs['tienda_nombre'] ?? 'SYS-COPM' }}</h2>
            <p style="margin: 2px 0;">RUC: {{ $configs['tienda_ruc'] ?? '20601234568' }} | {{ $configs['tienda_razon_social'] ?? '' }}</p>
            <p style="margin: 2px 0;">{{ $configs['tienda_direccion'] ?? '' }}</p>
            <p style="margin: 2px 0;">Tel: {{ $configs['tienda_telefono'] ?? '' }}</p>
        </div>
        <div class="doc-box">
            <h3>CONSTANCIA DE DEVOLUCIÓN</h3>
            <div style="font-size: 16px; font-weight: bold; margin-top: 5px;">{{ $devolucion->codigo }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">{{ $devolucion->created_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <strong>Datos del Cliente:</strong><br>
            Nombre / Razón Social: {{ $devolucion->cliente->nombre_razon_social }}<br>
            Documento: {{ $devolucion->cliente->tipo_documento->name }}: {{ $devolucion->cliente->numero_documento }}<br>
            Teléfono: {{ $devolucion->cliente->telefono ?? 'N/A' }}
        </div>
        <div class="info-box">
            <strong>Datos de la Operación:</strong><br>
            Venta Original: {{ $devolucion->venta->codigo }} ({{ $devolucion->venta->created_at->format('d/m/Y') }})<br>
            Tipo: {{ $devolucion->tipo->etiqueta() }}<br>
            Motivo: {{ $devolucion->motivo->etiqueta() }}<br>
            Estado: {{ $devolucion->estado->etiqueta() }}
        </div>
    </div>

    @if($devolucion->detalle_motivo)
        <div class="info-box" style="margin-bottom: 20px;">
            <strong>Detalle de la Devolución:</strong><br>
            {{ $devolucion->detalle_motivo }}
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Serie Devuelta</th>
                <th>Estado Físico</th>
                <th>Destino</th>
                <th style="text-align: right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($devolucion->detalles as $det)
                <tr>
                    <td>{{ $det->producto->nombre }}</td>
                    <td>{{ $det->serie?->numero_serie ?? 'Sin serie' }}</td>
                    <td>{{ $det->estado_fisico->etiqueta() }}</td>
                    <td>{{ ucfirst($det->destino_serie) }}</td>
                    <td style="text-align: right; font-weight: bold;">S/ {{ number_format((float) $det->monto_devuelto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; font-size: 14px;">TOTAL LIQUIDADO:</td>
                <td style="text-align: right; font-weight: bold; font-size: 14px; color: #1e3a8a;">S/ {{ number_format((float) $devolucion->total_devuelto, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="firmas">
        <div>
            <div class="linea-firma">
                Firma del Cliente<br>
                {{ $devolucion->cliente->nombre_razon_social }}<br>
                Doc: {{ $devolucion->cliente->numero_documento }}
            </div>
        </div>
        <div>
            <div class="linea-firma">
                Atendido por Tienda<br>
                {{ $devolucion->solicitante->name }}<br>
                SYS-COPM
            </div>
        </div>
    </div>
</body>
</html>
