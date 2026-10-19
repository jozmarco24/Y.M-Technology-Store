# RESPALDOS Y RESTAURACIÓN DE BASE DE DATOS - SYS-COPM

Este documento describe la política y procedimientos de copia de seguridad y recuperación probada ante desastres para PostgreSQL.

---

## 1. Copias de Seguridad Automáticas (Dump de PostgreSQL)

Para generar un volcado comprimido completo de la base de datos:

```bash
# Formato binario comprimido pg_dump (recomendado para producción)
pg_dump -U copm_user -h localhost -F c -b -v -f "/backups/sys_copm_$(date +\%Y\%m\%d_\%H\%M\%S).dump" sys_copm_prod
```

### Script de Respaldo Diario con Retención de 14 Días:
Guardar en `/usr/local/bin/backup-copm.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/sys-copm"
FECHA=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

# Generar dump
export PGPASSWORD="TU_PASSWORD_DB"
pg_dump -U copm_user -h localhost -F c -b -f "$BACKUP_DIR/copm_prod_$FECHA.dump" sys_copm_prod

# Eliminar respaldos con más de 14 días
find "$BACKUP_DIR" -type f -name "*.dump" -mtime +14 -exec rm {} \;

echo "Respaldo completado: $BACKUP_DIR/copm_prod_$FECHA.dump"
```

Programar en crontab root a las 02:00 AM:
```cron
0 2 * * * /usr/local/bin/backup-copm.sh >> /var/log/sys-copm-backup.log 2>&1
```

---

## 2. Procedimiento de Restauración Probado Paso a Paso

Para restaurar la base de datos en un entorno limpio o servidor de contingencia:

1. **Detener servicios que escriban en base de datos:**
   ```bash
   sudo supervisorctl stop sys-copm-worker:*
   php /var/www/sys-copm/artisan down
   ```

2. **Crear o limpiar la base de datos destino:**
   ```bash
   sudo -u postgres psql -c "DROP DATABASE IF EXISTS sys_copm_restore;"
   sudo -u postgres psql -c "CREATE DATABASE sys_copm_restore WITH OWNER copm_user;"
   ```

3. **Ejecutar restauración con `pg_restore`:**
   ```bash
   pg_restore -U copm_user -h localhost -d sys_copm_restore -v "/var/backups/sys-copm/copm_prod_XXXXX.dump"
   ```

4. **Verificar integridad referencial y conteo de tablas:**
   ```bash
   sudo -u postgres psql -d sys_copm_restore -c "SELECT count(*) FROM ventas;"
   sudo -u postgres psql -d sys_copm_restore -c "SELECT count(*) FROM series;"
   sudo -u postgres psql -d sys_copm_restore -c "SELECT count(*) FROM pedidos;"
   ```

5. **Reactivar la aplicación:**
   ```bash
   php /var/www/sys-copm/artisan up
   sudo supervisorctl start sys-copm-worker:*
   ```
