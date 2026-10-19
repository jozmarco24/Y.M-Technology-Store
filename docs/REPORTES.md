# Definiciones y Métricas de Reportes del Sistema

Este documento establece las definiciones matemáticas, tributarias y operativas oficiales para todas las métricas del módulo de analítica y reportes (`app/Services/Reportes/`).

---

## 1. Principio de Definición Única

Todas las consultas y servicios derivan de una única fuente de verdad:
- **Moneda**: Soles peruanos (`PEN / S/`).
- **Zona Horaria**: `America/Lima` (UTC-5).
- **Precisión Aritmética**: 2 decimales para dinero, operaciones con funciones de agregación nativas en PostgreSQL y redondeo estándar.

---

## 2. Ventas y Facturación

### Venta Bruta Facturada
- **Definición**: Suma total de los comprobantes de venta emitidos en estado `completada`, con IGV incluido antes de deducir devoluciones.
- **Fórmula**:
  $$\text{Venta Bruta} = \sum \text{ventas.total} \quad \text{donde } \text{ventas.estado} = 'completada'$$
- **Exclusiones**: No incluye ventas con estado `anulada` ni pedidos en borrador/pendientes.

### Venta Neta
- **Definición**: Venta total efectivamente consolidada tras deducir las devoluciones aprobadas y completadas asociadas a las ventas del periodo.
- **Fórmula**:
  $$\text{Venta Neta} = \text{Venta Bruta} - \sum \text{devoluciones.total\_devuelto} \quad (\text{estado} = 'completada')$$
- **Implementación SQL**: Vista `v_ventas_netas`.

### Venta Neta Sin IGV (Base Imponible)
- **Definición**: Importe neto de venta antes del Impuesto General a las Ventas (18%), equivalente a la suma de subtotales de línea con descuentos aplicados.
- **Fórmula**:
  $$\text{Venta Neta Sin IGV} = \sum \text{venta\_detalles.subtotal}$$

### Ticket Promedio
- **Definición**: Importe medio facturado por transacción de venta.
- **Fórmula**:
  $$\text{Ticket Promedio} = \frac{\text{Venta Bruta Facturada}}{\text{Total de Pedidos/Ventas Completadas}}$$

### Unidades por Venta (UPV / Basket Size)
- **Definición**: Cantidad promedio de productos individuales adquiridos por transacción.
- **Fórmula**:
  $$\text{UPV} = \frac{\sum \text{venta\_detalles.cantidad}}{\text{Total de Pedidos/Ventas Completadas}}$$

---

## 3. Rentabilidad y Márgenes

> [!IMPORTANT]
> **Seguridad y Confidencialidad**: Los costos y márgenes son información estrictamente confidencial. Solo los usuarios con rol `admin` o con el permiso `reportes.ver_margen` reciben estos campos. A nivel de Service, si el usuario no tiene dicho permiso, las columnas de costo y margen se omiten en el arreglo devuelto.

### Costo de Mercadería Vendida (COGS / Costo Total)
- **Definición**: Suma del costo histórico unitario de cada unidad o serie vendida, congelado en `venta_detalles.costo_unitario` al momento del despacho.
- **Fórmula**:
  $$\text{Costo Total} = \sum (\text{venta\_detalles.cantidad} \times \text{venta\_detalles.costo\_unitario})$$

### Margen Bruto en Monto (S/)
- **Definición**: Beneficio monetario obtenido tras deducir el costo de compra de la venta neta sin IGV.
- **Fórmula**:
  $$\text{Margen Bruto (S/)} = \text{Venta Neta Sin IGV} - \text{Costo Total}$$

### Margen Porcentual (%)
- **Definición**: Porcentaje de rentabilidad sobre la venta neta sin IGV.
- **Fórmula**:
  $$\text{Margen (\%)} = \left( \frac{\text{Margen Bruto (S/)}}{\text{Venta Neta Sin IGV}} \right) \times 100$$

### Ventas Bajo Margen o Bajo Costo
- **Alerta de Margen Bajo**: Operaciones donde el $\text{Margen (\%)} \le \text{umbral\_margen\_bajo}$ (predeterminado: 10%).
- **Venta Bajo Costo**: Operaciones donde $\text{Margen Bruto (S/)} < 0$.

---

## 4. Gestión y Eficiencia de Inventario

### Valorización de Inventario
- **Definición**: Capital total inmovilizado en almacén.
- **Fórmula**:
  $$\text{Valor Total} = \sum \text{series.costo} \quad (\text{estado} = 'disponible')$$

### Rotación de Inventario (Inventory Turnover)
- **Definición**: Frecuencia con la que el stock se consume y renueva en un periodo determinado.
- **Fórmula**:
  $$\text{Rotación} = \frac{\text{Unidades Vendidas en el Periodo}}{\text{Stock Promedio}}$$
  $$\text{Stock Promedio} \approx \frac{\text{Stock Actual} + (\text{Stock Actual} + \text{Unidades Vendidas})}{2}$$

### Días de Inventario (Days Sales of Inventory - DSI)
- **Definición**: Días proyectados que durará el inventario actual al ritmo de ventas observado.
- **Fórmula**:
  $$\text{Días de Inventario} = \frac{\text{Días del Periodo}}{\text{Rotación}}$$

### Stock Muerto
- **Definición**: Productos con unidades disponibles en almacén que no han registrado ninguna venta en los últimos $N$ días (configurable en `dias_stock_muerto`, predeterminado: 90 días).
- **Implementación SQL**: Cruce con la vista `v_ultima_venta_producto`.

### Sugerencia de Reposición
- **Definición**: Cantidad recomendada a comprar para satisfacer la demanda de los próximos $N$ días de cobertura configurada (`dias_cobertura_reposicion`, predeterminado: 30 días), respetando el `stock_minimo`.
- **Fórmula**:
  $$\text{Promedio Diario} = \frac{\text{Ventas de los últimos } D \text{ días}}{D}$$
  $$\text{Demanda Esperada} = \lceil \text{Promedio Diario} \times \text{Días Cobertura Deseada} \rceil$$
  $$\text{Cantidad Sugerida} = \max(0, (\text{Demanda Esperada} + \text{stock\_minimo}) - \text{Stock Actual})$$

### Clasificación ABC (Contribución al Margen)
- **Clase A**: Productos que generan hasta el **80%** del margen acumulado (o de la venta neta si no es admin).
- **Clase B**: Productos en el rango del **80% al 95%** de la contribución acumulada.
- **Clase C**: Productos de cola larga que representan el **5% final** (del 95% al 100%).

---

## 5. Postventa y Tasa de Fallas

### Tasa de Fallas por Modelo
- **Definición**: Proporción de equipos que ingresan a servicio técnico o garantía en relación al total de unidades vendidas de ese modelo.
- **Fórmula**:
  $$\text{Tasa de Fallas (\%)} = \left( \frac{\text{Total Órdenes de Servicio}}{\text{Unidades Vendidas Históricas}} \right) \times 100$$

### Tasa de Devolución
- **Fórmula**:
  $$\text{Tasa de Devolución (\%)} = \left( \frac{\text{Total Monto Devuelto}}{\text{Venta Bruta Facturada}} \right) \times 100$$

---

## 6. Rendimiento y Caché de Consultas

1. **Caché Versionada**:
   - Clave: `rep:v{version}:{reporte}:usr{id}:m{0|1}:{hash_filtros}`
   - TTL: 10 minutos.
   - **Invalidación Inmediata**: Al registrar una venta (`VentaService`) o al recibir una compra de mercadería (`InventarioService`), se incrementa `reportes_cache_version`, invalidando de forma instantánea todos los reportes agregados.
2. **Índices en PostgreSQL**:
   - `idx_ventas_reportes_fecha_estado_canal` en `ventas(created_at, estado, canal)`.
   - `idx_venta_detalles_prod_venta` en `venta_detalles(producto_id, venta_id)`.
   - `idx_series_estado_producto` en `series(estado, producto_id)`.
   - `idx_movimientos_prod_fecha` en `movimientos_inventario(producto_id, created_at)`.
   - `idx_compra_detalles_prod_compra` en `compra_detalles(producto_id, compra_id)`.

---

## 7. Centro de Reportes en Filament (Fase 5B)

El Centro de Reportes está organizado bajo el grupo de navegación **"Reportes"** en Filament 3.

### 7.1 Páginas y Control de Accesos

| Página | Ruta / Slug | Permiso Requerido | Restricciones de Visualización |
| :--- | :--- | :--- | :--- |
| **Resumen Ejecutivo** | `/admin/reportes/resumen-ejecutivo` | `reportes.ver_resumen` | Solo visible para `admin` o roles con permiso expreso. Muestra KPIs comerciales, alertas activas y comparativa con periodo anterior. |
| **Rentabilidad** | `/admin/reportes/rentabilidad` | `reportes.ver_margen` | Oculta para vendedores y personal de almacén. Exclusivo para administradores y analistas financieros. |
| **Inventario** | `/admin/reportes/inventario` | `reportes.ver_inventario` | Accesible por almacén y admin. El valor monetario de inventario y capital inmovilizado solo se muestra si tiene `reportes.ver_margen`. Incluye botón **"Crear compra borrador"** para reposición sugerida. |
| **Ventas** | `/admin/reportes/ventas` | `reportes.ver_ventas` | KPIs de volumen, funnel web, participación por medio de pago y distribución horaria/semanal. |
| **Compras** | `/admin/reportes/compras` | `reportes.ver_compras` | Desglose por proveedor, evolución de costo por SKU y comparativa de cotización entre proveedores. |
| **Postventa** | `/admin/reportes/postventa` | `reportes.ver_postventa` | Tasa de fallas por modelo, tiempos de reparación y órdenes con retraso. |
| **Caja** | `/admin/reportes/caja` | `reportes.ver_caja` | Descuadres al cierre de caja, anulaciones de venta y movimientos de egreso/ingreso manual. |
| **Vendedores** | `/admin/reportes/vendedores` | `reportes.ver_vendedores` | Ranking por vendedor, ventas totales, descuentos y anulaciones. Margen visible solo con `reportes.ver_margen`. Comisiones visibles si `activar_comisiones = 1`. |
| **Ajustes de Reportes** | `/admin/reportes/ajustes-reportes` | Solo `admin` | Parámetros operacionales de umbrales, destinatarios y prueba de correo. |

### 7.2 Filtros Globales de Reporte
Todas las pantallas de analítica heredan de `BaseReportePage`, disponiendo de:
- **Atajos de fecha**: `hoy`, `7_dias`, `mes` (mes actual), `mes_anterior`, `anio` (año actual) y `personalizado`.
- **Canal de Venta**: `todos`, `pos`, `web`.
- **Comparar con periodo anterior**: Activa cálculo diferencial $\Delta\%$ con flechas verdes/rojas.
- **Persistencia en URL**: Los parámetros `fechaDesde`, `fechaHasta` y `canal` viajan en el query string de Livewire para permitir marcadores o compartir enlaces.

### 7.3 Exportaciones y Trazabilidad
- Formatos disponibles: **CSV**, **Excel** (.xlsx vía Spout o PhpSpreadsheet) y **PDF** (vía DomPDF con encabezado oficial y fecha de generación).
- Todo intento de exportación valida el permiso `reportes.exportar`.
- **Audit Log**: Cada descarga genera un registro de auditoría en `activity_log` (`exportar_reporte`) registrando el usuario, nombre del reporte, formato y los filtros aplicados.
- **Ocultamiento de Costos**: Si el usuario que exporta carece de `reportes.ver_margen`, las columnas de costo unitario, costo total y margen se eliminan de la estructura del archivo descargado.

### 7.4 Parámetros Configurables (`Ajustes de Reportes`)
- `dias_stock_muerto`: Días sin ventas para clasificar un producto como stock muerto (por defecto `90`).
- `dias_cobertura_reposicion`: Días de cobertura histórica esperada (por defecto `30`).
- `umbral_margen_bajo`: Porcentaje umbral de advertencia para margen comercial (por defecto `10%`).
- `destinatarios_resumen_semanal`: Lista de correos separados por coma para el informe ejecutivo.
- `dia_resumen_semanal`: Día de envío programado (`Monday`, `Friday`, `Saturday`).
- `activar_comisiones`: Interruptor global para activar el cálculo de comisiones a la fuerza de ventas.

### 7.5 Comando de Envío Programado
Para generar y despachar el correo con el Resumen Ejecutivo semanal:
```bash
php artisan reportes:enviar-resumen-semanal
```
Este comando consulta los destinatarios configurados, procesa los KPIs de los últimos 7 días y encola el mailable con el informe a través del job `EnviarResumenSemanalEmailJob`.

