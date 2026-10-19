# RUNBOOK OPERATIVO DE PRODUCCIÓN - SYS-COPM

Este documento detalla los procedimientos de contingencia, soporte y resolución de incidentes para el entorno de producción de **SYS-COPM**.

---

## 1. Contingencia: Caída o Falla de Nubefact (Facturación SUNAT)

### Diagnóstico:
- Los pedidos y ventas en POS/Web siguen registrándose con normalidad.
- Los registros en `comprobantes_electronicos` quedan en estado `error` o `pendiente` con `intentos >= 1`.
- El cliente recibe su ticket/resumen pero no el PDF/XML de SUNAT.

### Procedimiento:
1. **No anular ni duplicar ventas:** La venta ya es válida en base de datos.
2. Comprobar conectividad y estado del servicio de Nubefact:
   ```bash
   curl -I https://api.nubefact.com
   ```
3. Una vez restaurado el servicio de Nubefact, el scheduler reintentará automáticamente cada 10 minutos (`reintentar-comprobantes-electronicos`).
4. Para forzar reintento manual inmediato:
   ```bash
   php artisan queue:work --queue=facturacion --once
   ```

---

## 2. Contingencia: Caída de la Pasarela de Pagos (Culqi / Niubiz)

### Diagnóstico:
- Clientes reportan error o timeout en el paso de pago del checkout.
- `pagos_online` tiene registros en `pendiente` o `fallido`.

### Procedimiento:
1. **Evitar doble cobro:** Nunca pedir al cliente que vuelva a pagar sin verificar antes en el dashboard de Culqi.
2. Ejecutar la conciliación automática para contrastar cargos pendientes:
   ```bash
   php artisan pagos:conciliar --dias=1
   ```
3. Si la pasarela sufre una caída prolongada:
   - Activar temporalmente el método de **Yape manual / Transferencia bancaria** informando a los clientes en el banner superior.
   - El admin validará los comprobantes bancarios en Filament (`PedidoResource` -> *Confirmar Pago Manual*).

---

## 3. Contingencia: Alerta de Espacio en Disco Lleno (>= 90%)

### Diagnóstico:
- Logs reportan `No space left on device`.
- `php artisan tienda:salud` reporta espacio libre crítico (< 2 GB).

### Procedimiento:
1. Identificar directorios con mayor consumo:
   ```bash
   df -h
   du -sh /var/www/sys-copm/storage/logs/*
   du -sh /var/log/*
   ```
2. Rotar y truncar logs antiguos:
   ```bash
   > /var/www/sys-copm/storage/logs/laravel.log
   journalctl --vacuum-time=3d
   ```
3. Limpiar paquetes de composer y cache:
   ```bash
   php artisan view:clear
   php artisan cache:clear
   ```

---

## 4. Contingencia: Atraso o Bloqueo en Colas de Trabajo (Queue Workers)

### Diagnóstico:
- Envíos de emails o emisión de comprobantes tardan más de lo previsto.
- `jobs` acumulados en base de datos.

### Procedimiento:
1. Consultar estado con el comando de salud:
   ```bash
   php artisan tienda:salud
   ```
2. Reiniciar workers de Supervisor:
   ```bash
   sudo supervisorctl restart all
   php artisan queue:restart
   ```
3. Inspeccionar jobs fallidos:
   ```bash
   php artisan queue:failed
   php artisan queue:retry all
   ```

---

## 5. Procedimiento de Conciliación y Reembolsos

### Conciliación:
- Se ejecuta cada hora en cron o bajo demanda:
  ```bash
  php artisan pagos:conciliar --dias=2
  ```

### Reembolsos:
1. Acceder al panel de Filament -> **Pedidos Online**.
2. Ubicar el pedido en estado `pagado` o `reembolsado`.
3. Seleccionar la acción **Reembolsar** indicando el motivo formal.
4. El sistema invoca la API de la pasarela y registra el movimiento contable correspondiente.
