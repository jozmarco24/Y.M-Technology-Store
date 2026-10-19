# GUÍA DE DESPLIEGUE EN VPS UBUNTU 24.04 / 22.04 - SYS-COPM

Esta guía describe el aprovisionamiento paso a paso en un VPS (Hetzner, DigitalOcean, Linode, AWS) para producción.

---

## 1. Actualización y Paquetes Base

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql postgresql-contrib php8.3-fpm php8.3-cli php8.3-pgsql \
    php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-intl \
    supervisor git curl unzip ufw fail2ban
```

---

## 2. Configuración de Base de Datos PostgreSQL

```bash
sudo -u postgres psql
```
```sql
CREATE DATABASE sys_copm_prod;
CREATE USER copm_user WITH ENCRYPTED PASSWORD 'TU_PASSWORD_SEGURO_AQUI';
GRANT ALL PRIVILEGES ON DATABASE sys_copm_prod TO copm_user;
\q
```

---

## 3. Despliegue del Código y Permisos

```bash
sudo mkdir -p /var/www/sys-copm
sudo chown -R $USER:www-data /var/www/sys-copm
cd /var/www/sys-copm
git clone https://github.com/tu-organizacion/sys-copm.git .

composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env # Configurar credenciales DB, pasarelas, app_key
php artisan key:generate

sudo chown -R www-data:www-data /var/www/sys-copm/storage /var/www/sys-copm/bootstrap/cache
sudo chmod -R 775 /var/www/sys-copm/storage /var/www/sys-copm/bootstrap/cache

php artisan migrate --force --seed
php artisan storage:link
```

---

## 4. Configuración de Nginx

Crear `/etc/nginx/sites-available/sys-copm`:

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;
    root /var/www/sys-copm/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Habilitar sitio y generar certificado SSL gratuito con Let's Encrypt:
```bash
sudo ln -s /etc/nginx/sites-available/sys-copm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tudominio.com -d www.tudominio.com
```

---

## 5. Configuración de Supervisor para Workers de Colas

Crear archivo `/etc/supervisor/conf.d/sys-copm-worker.conf`:

```ini
[program:sys-copm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sys-copm/artisan queue:work --queue=facturacion,pagos,emails,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sys-copm/storage/logs/worker.log
stopwaitsecs=3600
```

Iniciar supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sys-copm-worker:*
```

---

## 6. Cron del Scheduler de Laravel

Editar crontab con `sudo crontab -u www-data -e` y añadir:

```cron
* * * * * cd /var/www/sys-copm && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Script de Despliegue Zero-Downtime

Guardar como `deploy.sh`:

```bash
#!/bin/bash
set -e

echo "Iniciando despliegue de SYS-COPM..."
php artisan down || true

git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

php artisan queue:restart
php artisan up

echo "Despliegue finalizado con éxito."
```
