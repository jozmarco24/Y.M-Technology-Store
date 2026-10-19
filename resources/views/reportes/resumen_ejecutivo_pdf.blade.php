<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Ejecutivo - {{ $periodo['desde'] }} al {{ $periodo['hasta'] }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; margin: 20px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 18px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 12px; }
        .card-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; color: #334155; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-alert { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-bottom: 4px; display: block; }
        .metric-val { font-size: 14px; font-weight: bold; color: #1e3a8a; }
        .metric-label { font-size: 10px; color: #64748b; text-transform: uppercase; }
        .grid-2 { width: 100%; margin-bottom: 10px; }
        .grid-2 td { border: none; padding: 4px; vertical-align: top; width: 50%; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <table style="border: none; width: 100%;">
            <tr style="border: none;">
                <td style="border: none; width: 70%;">
                    <div class="title">{{ $tienda_nombre ?? 'TIENDA DE TECNOLOGÍA' }}</div>
                    <div class="subtitle">Informe Ejecutivo Semanal &bull; Periodo: {{ $periodo['desde'] }} al {{ $periodo['hasta'] }}</div>
                </td>
                <td style="border: none; text-align: right; width: 30%;">
                    <div style="font-size: 10px; color: #64748b;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
                    <div style="font-size: 10px; color: #64748b;">Moneda: Soles (PEN)</div>
                </td>
            </tr>
        </table>
    </div>

    @if (!empty($alertas))
        <div class="card" style="border-left: 4px solid #ef4444; background: #fff5f5;">
            <div class="card-title" style="color: #991b1b;">⚠️ Alertas y Riesgos Operativos</div>
            @foreach ($alertas as $alerta)
                <div class="badge-alert">&bull; {{ $alerta }}</div>
            @endforeach
        </div>
    @endif

    <table class="grid-2">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Desempeño de Ventas</div>
                    <table>
                        <tr>
                            <th>Métrica</th>
                            <th class="text-right">Valor</th>
                        </tr>
                        <tr>
                            <td>Total Pedidos</td>
                            <td class="text-right"><strong>{{ $ventas['total_pedidos'] }}</strong></td>
                        </tr>
                        <tr>
                            <td>Venta Neta (sin devoluciones)</td>
                            <td class="text-right"><strong>S/ {{ number_format($ventas['venta_neta'], 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Ticket Promedio</td>
                            <td class="text-right">S/ {{ number_format($ventas['ticket_promedio'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Unidades por Venta</td>
                            <td class="text-right">{{ $ventas['unidades_por_venta'] }}</td>
                        </tr>
                        <tr>
                            <td>Variación vs Periodo Anterior</td>
                            <td class="text-right" style="color: {{ $ventas['variacion_ventas_porcentaje'] >= 0 ? '#16a34a' : '#dc2626' }};">
                                <strong>{{ $ventas['variacion_ventas_porcentaje'] >= 0 ? '+' : '' }}{{ $ventas['variacion_ventas_porcentaje'] }} %</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                @if (isset($rentabilidad))
                    <div class="card">
                        <div class="card-title">Rentabilidad & Márgenes</div>
                        <table>
                            <tr>
                                <th>Métrica</th>
                                <th class="text-right">Valor</th>
                            </tr>
                            <tr>
                                <td>Margen Bruto del Periodo</td>
                                <td class="text-right"><strong>S/ {{ number_format($rentabilidad['margen_bruto'], 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Margen Periodo Anterior</td>
                                <td class="text-right">S/ {{ number_format($rentabilidad['margen_anterior'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Variación de Margen</td>
                                <td class="text-right" style="color: {{ $rentabilidad['variacion_margen_porcentaje'] >= 0 ? '#16a34a' : '#dc2626' }};">
                                    <strong>{{ $rentabilidad['variacion_margen_porcentaje'] >= 0 ? '+' : '' }}{{ $rentabilidad['variacion_margen_porcentaje'] }} %</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                @else
                    <div class="card">
                        <div class="card-title">Operaciones & Caja</div>
                        <table>
                            <tr>
                                <td>Diferencia Acumulada de Caja</td>
                                <td class="text-right">S/ {{ number_format($operaciones['diferencia_caja_acumulada'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Comprobantes con Error / Rechazo</td>
                                <td class="text-right">{{ $operaciones['comprobantes_con_problemas'] }}</td>
                            </tr>
                        </table>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="card">
        <div class="card-title">Top 5 Productos Vendidos</div>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th>Categoría</th>
                    <th class="text-center">Unidades</th>
                    <th class="text-right">Venta Neta</th>
                    @if (isset($rentabilidad))
                        <th class="text-right">Margen (S/)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($top_productos as $prod)
                    <tr>
                        <td>{{ $prod['producto_nombre'] }}</td>
                        <td>{{ $prod['sku'] }}</td>
                        <td>{{ $prod['categoria'] }}</td>
                        <td class="text-center">{{ $prod['unidades_vendidas'] }}</td>
                        <td class="text-right">S/ {{ number_format($prod['venta_neta_sin_igv'], 2) }}</td>
                        @if (isset($rentabilidad))
                            <td class="text-right">S/ {{ number_format($prod['margen_monto'] ?? 0, 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ isset($rentabilidad) ? '6' : '5' }}" class="text-center">No se registraron ventas en el periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-title">Estado de Inventario & Postventa</div>
        <table style="width: 100%;">
            <tr>
                <td style="width: 25%; text-align: center;">
                    <div class="metric-val">{{ $inventario['productos_stock_critico'] }}</div>
                    <div class="metric-label">Productos Stock Crítico</div>
                </td>
                <td style="width: 25%; text-align: center;">
                    <div class="metric-val">{{ $inventario['productos_stock_muerto'] }}</div>
                    <div class="metric-label">Productos Stock Muerto</div>
                </td>
                <td style="width: 25%; text-align: center;">
                    <div class="metric-val">S/ {{ number_format($postventa['total_devuelto'], 2) }}</div>
                    <div class="metric-label">Devoluciones ({{ $postventa['tasa_devolucion_porcentaje'] }}%)</div>
                </td>
                <td style="width: 25%; text-align: center;">
                    <div class="metric-val">{{ $postventa['garantias_por_vencer'] }}</div>
                    <div class="metric-label">Garantías por Vencer</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Documento confidencial emitido para la gerencia general y administración. Sistema ERP SYS-COPM &bull; Página 1 de 1
    </div>

</body>
</html>
