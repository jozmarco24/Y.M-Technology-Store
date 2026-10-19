<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Ingreso - {{ $orden->codigo }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #333; margin: 30px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px; }
        .empresa h2 { margin: 0; color: #0369a1; font-size: 20px; }
        .doc-box { border: 2px solid #0284c7; padding: 10px 20px; text-align: center; border-radius: 6px; }
        .doc-box h3 { margin: 0; color: #0284c7; font-size: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 6px; }
        .qr-placeholder { border: 1px dashed #94a3b8; width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; font-size: 10px; margin: 0 auto; text-align: center; }
        .condiciones { font-size: 11px; color: #475569; background: #fffbeb; border: 1px solid #fef3c7; padding: 12px; border-radius: 6px; margin-top: 25px; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; text-align: center; }
        .linea-firma { border-top: 1px solid #94a3b8; padding-top: 5px; font-weight: bold; font-size: 12px; }
        .no-print { margin-bottom: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0284c7; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Orden de Ingreso A4
        </button>
    </div>

    <div class="header">
        <div class="empresa">
            <h2>{{ $configs['tienda_nombre'] ?? 'SYS-COPM' }}</h2>
            <p style="margin: 2px 0;">SERVICIO TÉCNICO ESPECIALIZADO</p>
            <p style="margin: 2px 0;">RUC: {{ $configs['tienda_ruc'] ?? '20601234568' }} | Tel: {{ $configs['tienda_telefono'] ?? '' }}</p>
            <p style="margin: 2px 0;">{{ $configs['tienda_direccion'] ?? '' }}</p>
        </div>
        <div class="doc-box">
            <h3>ORDEN DE INGRESO</h3>
            <div style="font-size: 18px; font-weight: bold; margin-top: 4px;">{{ $orden->codigo }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ $orden->recibido_en->format('d/m/Y H:i') }}</div>
            <div style="margin-top: 6px;">
                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; {{ $orden->esGarantia() ? 'background: #dcfce7; color: #15803d;' : 'background: #e0f2fe; color: #0369a1;' }}">
                    ATENCIÓN: {{ strtoupper($orden->tipo->etiqueta()) }}
                </span>
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <strong>Datos del Cliente:</strong><br>
            Nombre: {{ $orden->cliente->nombre_razon_social }}<br>
            Doc: {{ $orden->cliente->tipo_documento->name }}: {{ $orden->cliente->numero_documento }}<br>
            Teléfono: {{ $orden->cliente->telefono ?? 'N/A' }}<br>
            Email: {{ $orden->cliente->email ?? 'N/A' }}
        </div>
        <div class="info-box">
            <strong>Datos del Equipo:</strong><br>
            Equipo: {{ $orden->producto?->nombre ?? $orden->equipo_descripcion }}<br>
            Serie: {{ $orden->serie?->numero_serie ?? ($orden->serie_externa ?: 'Sin serie registrada') }}<br>
            Recepción por: {{ $orden->receptor->name }}<br>
            Fecha Promesa: {{ $orden->fecha_promesa ? $orden->fecha_promesa->format('d/m/Y') : 'Por coordinar' }}
        </div>
    </div>

    <div class="info-box" style="margin-bottom: 15px;">
        <strong>Falla Reportada por el Cliente:</strong><br>
        {{ $orden->falla_reportada }}
    </div>

    <div class="info-box" style="margin-bottom: 15px;">
        <strong>Estado Físico en Recepción y Accesorios Entregados:</strong><br>
        {{ $orden->estado_recepcion }}
    </div>

    <div class="condiciones">
        <strong>TÉRMINOS Y CONDICIONES DEL SERVICIO:</strong><br>
        1. Todo diagnóstico se emitirá en un plazo estimado de 24 a 48 horas hábiles.<br>
        2. La empresa no se responsabiliza por la pérdida de información o datos almacenados en los dispositivos. Es responsabilidad exclusiva del cliente realizar copias de respaldo.<br>
        3. <strong>Plazo de recojo:</strong> Pasados los 30 días calendario desde la notificación de finalización, se aplicará costo de almacenaje (S/ 5.00 diarios). Pasados los 90 días, el equipo se considerará en abandono conforme al Código Civil.<br>
        4. Toda reparación cuenta con <strong>{{ $orden->garantia_reparacion_dias }} días de garantía</strong> sobre la mano de obra y componentes reemplazados.
    </div>

    <div class="firmas">
        <div>
            <div class="linea-firma">
                Firma y Conformidad del Cliente<br>
                {{ $orden->cliente->nombre_razon_social }}<br>
                Doc: {{ $orden->cliente->numero_documento }}
            </div>
        </div>
        <div>
            <div class="linea-firma">
                Recepción Servicio Técnico<br>
                {{ $orden->receptor->name }}<br>
                SYS-COPM
            </div>
        </div>
    </div>
</body>
</html>
