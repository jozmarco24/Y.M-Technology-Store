<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Entrega y Garantía - {{ $orden->codigo }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #333; margin: 30px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #16a34a; padding-bottom: 15px; margin-bottom: 20px; }
        .empresa h2 { margin: 0; color: #15803d; font-size: 20px; }
        .doc-box { border: 2px solid #16a34a; padding: 10px 20px; text-align: center; border-radius: 6px; }
        .doc-box h3 { margin: 0; color: #16a34a; font-size: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 6px; }
        .garantia-box { background: #eff6ff; border: 2px solid #3b82f6; padding: 15px; border-radius: 8px; margin: 25px 0; text-align: center; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; text-align: center; }
        .linea-firma { border-top: 1px solid #94a3b8; padding-top: 5px; font-weight: bold; font-size: 12px; }
        .no-print { margin-bottom: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Acta de Entrega A4
        </button>
    </div>

    <div class="header">
        <div class="empresa">
            <h2>{{ $configs['tienda_nombre'] ?? 'SYS-COPM' }}</h2>
            <p style="margin: 2px 0;">ACTA DE CONFORMIDAD Y ENTREGA TÉCNICA</p>
            <p style="margin: 2px 0;">RUC: {{ $configs['tienda_ruc'] ?? '20601234568' }} | Tel: {{ $configs['tienda_telefono'] ?? '' }}</p>
        </div>
        <div class="doc-box">
            <h3>ACTA DE ENTREGA</h3>
            <div style="font-size: 18px; font-weight: bold; margin-top: 4px;">{{ $orden->codigo }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ $orden->entregado_en ? $orden->entregado_en->format('d/m/Y H:i') : Carbon\Carbon::now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <strong>Datos del Receptor / Cliente:</strong><br>
            Entregado a: <strong>{{ $orden->entregado_a ?? $orden->cliente->nombre_razon_social }}</strong><br>
            Cliente Titular: {{ $orden->cliente->nombre_razon_social }}<br>
            Doc. Identidad: {{ $orden->cliente->numero_documento }}
        </div>
        <div class="info-box">
            <strong>Datos del Equipo Entregado:</strong><br>
            Equipo: {{ $orden->producto?->nombre ?? $orden->equipo_descripcion }}<br>
            Número de Serie: {{ $orden->serie?->numero_serie ?? ($orden->serie_externa ?: 'S/N') }}<br>
            Atención: {{ strtoupper($orden->tipo->etiqueta()) }}
        </div>
    </div>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 6px; margin-bottom: 20px;">
        <strong>Diagnóstico Realizado:</strong><br>
        {{ $orden->diagnostico ?? 'Revisión y diagnóstico en laboratorio.' }}<br><br>
        <strong>Solución Técnica Implementada:</strong><br>
        {{ $orden->solucion ?? 'Reparación de hardware y pruebas de funcionamiento superadas con éxito.' }}
    </div>

    <div class="garantia-box">
        <div style="color: #1e40af; font-size: 14px; font-weight: bold;">CERTIFICADO DE GARANTÍA DE SERVICIO</div>
        <div style="font-size: 24px; font-weight: bold; color: #1d4ed8; margin: 6px 0;">
            {{ $orden->garantia_reparacion_dias }} DÍAS CALENDARIO
        </div>
        <div style="font-size: 12px; color: #3b82f6;">
            Vigente desde: {{ ($orden->entregado_en ?? Carbon\Carbon::now())->format('d/m/Y') }} hasta: {{ ($orden->entregado_en ?? Carbon\Carbon::now())->addDays($orden->garantia_reparacion_dias)->format('d/m/Y') }}
        </div>
        <div style="font-size: 11px; color: #475569; margin-top: 8px;">
            Cubre fallas en la mano de obra realizada y en los repuestos instalados. Quedan excluidos daños por golpes, humedad, fluctuaciones eléctricas o manipulación por terceros.
        </div>
    </div>

    <div class="firmas">
        <div>
            <div class="linea-firma">
                Conformidad del Cliente / Recibí Conforme<br>
                Nombre: {{ $orden->entregado_a ?? $orden->cliente->nombre_razon_social }}<br>
                DNI / Firma
            </div>
        </div>
        <div>
            <div class="linea-firma">
                Entregado por Tienda SYS-COPM<br>
                Firma y Sello Autorizado
            </div>
        </div>
    </div>
</body>
</html>
