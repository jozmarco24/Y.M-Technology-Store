<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización de Reparación - {{ $orden->codigo }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #333; margin: 30px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #eab308; padding-bottom: 15px; margin-bottom: 20px; }
        .empresa h2 { margin: 0; color: #854d0e; font-size: 20px; }
        .doc-box { border: 2px solid #eab308; padding: 10px 20px; text-align: center; border-radius: 6px; }
        .doc-box h3 { margin: 0; color: #a16207; font-size: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-box { background: #fefce8; border: 1px solid #fef08a; padding: 10px 14px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #ca8a04; color: white; padding: 8px 10px; text-align: left; font-size: 12px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; font-size: 12px; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; text-align: center; }
        .linea-firma { border-top: 1px solid #94a3b8; padding-top: 5px; font-weight: bold; font-size: 12px; }
        .no-print { margin-bottom: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #ca8a04; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Cotización A4
        </button>
    </div>

    <div class="header">
        <div class="empresa">
            <h2>{{ $configs['tienda_nombre'] ?? 'SYS-COPM' }}</h2>
            <p style="margin: 2px 0;">PRESUPUESTO DE SERVICIO TÉCNICO</p>
            <p style="margin: 2px 0;">RUC: {{ $configs['tienda_ruc'] ?? '20601234568' }} | Tel: {{ $configs['tienda_telefono'] ?? '' }}</p>
        </div>
        <div class="doc-box">
            <h3>COTIZACIÓN DE SERVICIO</h3>
            <div style="font-size: 18px; font-weight: bold; margin-top: 4px;">{{ $orden->codigo }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ Carbon\Carbon::now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <strong>Cliente:</strong> {{ $orden->cliente->nombre_razon_social }}<br>
            <strong>Documento:</strong> {{ $orden->cliente->numero_documento }}<br>
            <strong>Teléfono:</strong> {{ $orden->cliente->telefono ?? 'N/A' }}
        </div>
        <div class="info-box">
            <strong>Equipo:</strong> {{ $orden->producto?->nombre ?? $orden->equipo_descripcion }}<br>
            <strong>Serie:</strong> {{ $orden->serie?->numero_serie ?? ($orden->serie_externa ?: 'S/N') }}<br>
            <strong>Técnico Evaluador:</strong> {{ $orden->tecnico?->name ?? 'Laboratorio SYS-COPM' }}
        </div>
    </div>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <strong>Diagnóstico Técnico:</strong><br>
        {{ $orden->diagnostico ?? 'Evaluación preliminar de componentes y circuitos.' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Concepto / Repuesto</th>
                <th style="text-align: center;">Cantidad</th>
                <th style="text-align: right;">Precio Unit.</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orden->repuestos as $rep)
                <tr>
                    <td>{{ $rep->descripcion }}</td>
                    <td style="text-align: center;">{{ $rep->cantidad }}</td>
                    <td style="text-align: right;">S/ {{ number_format((float) $rep->precio_unitario, 2) }}</td>
                    <td style="text-align: right; font-weight: bold;">S/ {{ number_format((float) $rep->subtotalPrecio(), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Servicio de diagnóstico, reparación y mano de obra especializada</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: right;">S/ {{ number_format((float) ($orden->costo_estimado ?? 0), 2) }}</td>
                    <td style="text-align: right; font-weight: bold;">S/ {{ number_format((float) ($orden->costo_estimado ?? 0), 2) }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold; font-size: 14px;">TOTAL PRESUPUESTADO:</td>
                <td style="text-align: right; font-weight: bold; font-size: 14px; color: #a16207;">S/ {{ number_format((float) ($orden->costo_estimado ?? 0), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="font-size: 11px; color: #64748b; margin-top: 20px;">
        * Esta cotización tiene una vigencia de 7 días calendario a partir de su emisión.<br>
        * Garantía del servicio: {{ $orden->garantia_reparacion_dias }} días.
    </div>

    <div class="firmas">
        <div>
            <div class="linea-firma">
                Aprobación del Cliente<br>
                Firma y DNI
            </div>
        </div>
        <div>
            <div class="linea-firma">
                Técnico Responsable<br>
                SYS-COPM
            </div>
        </div>
    </div>
</body>
</html>
