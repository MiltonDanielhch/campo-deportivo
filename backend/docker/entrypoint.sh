#!/bin/sh
set -e

echo "🚀 Iniciando backend Laravel..."

# Crear directorios necesarios si no existen
mkdir -p /var/www/html/storage/framework/{cache,sessions,views}
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/bootstrap/cache

# Limpiar caché de packages (fix para Pail y otros dev-packages)
rm -f /var/www/html/bootstrap/cache/packages.php
rm -f /var/www/html/bootstrap/cache/services.php

# Permisos
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Esperar a que la base de datos esté lista
echo "⏳ Esperando a PostgreSQL..."
until php -r "
\$conn = @pg_connect('host=' . getenv('DB_HOST') . ' port=' . (getenv('DB_PORT') ?: '5432') . ' dbname=' . getenv('DB_DATABASE') . ' user=' . getenv('DB_USERNAME') . ' password=' . getenv('DB_PASSWORD'));
if (\$conn) { echo 'ok'; exit(0); } else { exit(1); }
" > /dev/null 2>&1; do
    echo "   ... PostgreSQL no está listo, reintentando en 2s"
    sleep 2
done
echo "✅ PostgreSQL está listo"

# Correr migraciones (idempotente)
echo "📦 Corriendo migraciones..."
php artisan migrate --force

# Cachear configuración para producción
echo "⚡ Cacheando configuración..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generar key si no existe
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generando APP_KEY..."
    php artisan key:generate --force
fi

# Link de storage (para imágenes de campos)
php artisan storage:link 2>/dev/null || true

# Crear directorio de logs de supervisor
mkdir -p /var/log/supervisor

echo "✅ Backend listo. Iniciando supervisord..."

exec "$@"
