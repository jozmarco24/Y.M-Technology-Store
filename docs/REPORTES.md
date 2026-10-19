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
