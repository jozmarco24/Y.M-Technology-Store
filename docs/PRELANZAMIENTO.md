# CHECKLIST DE PRE-LANZAMIENTO A PRODUCCIÓN - SYS-COPM

Antes de abrir la tienda online al público y aceptar pagos reales, verificar cada uno de los siguientes puntos:

---

## 1. Seguridad e Infraestructura
- [ ] Certificado SSL activo y forzado vía HTTPS con HSTS activado (`Strict-Transport-Security`).
- [ ] Variables de producción configuradas:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_HTTP_ONLY=true`
- [ ] Firewall `ufw` en el servidor con solo puertos 22 (SSH), 80 (HTTP) y 443 (HTTPS) abiertos.
- [ ] `fail2ban` activo protegiendo accesos SSH.
- [ ] Permisos de directorios: `storage/` y `bootstrap/cache/` con permisos de escritura solo para `www-data`.

---

## 2. Pasarelas de Pago y Facturación Electrónica
- [ ] Llaves de producción de Culqi cargadas (`CULQI_PUBLIC_KEY`, `CULQI_SECRET_KEY`, `CULQI_WEBHOOK_SECRET`).
- [ ] Endpoint de webhook registrado en el dashboard de Culqi: `https://tudominio.com/webhooks/culqi`.
- [ ] Token y ruta de producción de Nubefact configurados en `configuraciones` o `.env`.
- [ ] Series electrónicas autorizadas por SUNAT creadas y activas (B001 para Boleta, F001 para Factura).

---

## 3. Legal y Cumplimiento Normativo (Perú)
- [ ] Libro de Reclamaciones virtual operativo y visible en el pie de página con plazo de 15 días hábiles conforme a INDECOPI.
- [ ] Páginas informativas publicadas:
  - Términos y Condiciones
  - Política de Privacidad (conforme a Ley de Protección de Datos Personales N° 29733)
  - Políticas de Envíos, Cambios y Devoluciones
- [ ] Banner de consentimiento de cookies activo.

---

## 4. Respaldos y Recuperación ante Desastres
- [ ] Tarea programada en cron para respaldo diario de PostgreSQL.
- [ ] Restauración probada exitosamente en un entorno de pruebas o servidor secundario.
- [ ] Copia externa de seguridad configurada hacia almacenamiento en la nube (S3 / Backblaze B2).

---

## 5. Rendimiento y Colas
- [ ] Supervisor configurado y ejecutando workers para colas:
  - `facturacion`
  - `pagos`
  - `emails`
  - `default`
- [ ] Cron de Laravel activo en el sistema operativo:
  ```cron
  * * * * * cd /var/www/sys-copm && php artisan schedule:run >> /dev/null 2>&1
  ```
- [ ] Comandos de optimización ejecutados:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan filament:cache-components
  ```

---

## 6. Monitoreo y Notificaciones
- [ ] Monitor de disponibilidad (UptimeRobot o Better Uptime) configurado apuntando a `https://tudominio.com/up`.
- [ ] Servicio de reporte de excepciones y errores configurado (Sentry o Flare).
- [ ] Registro en Google Search Console y envío de `sitemap.xml`.
