#!/bin/bash

# 🚀 Build Script para Render.com
# Este script se ejecuta automáticamente en Render

set -e

echo "🔨 Iniciando build de RuedaExpress Backend..."

# Instalar dependencias de Composer
echo "📦 Instalando dependencias de PHP..."
composer install --optimize-autoloader --no-dev

# Generar clave de aplicación si no existe
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generando clave de aplicación..."
    php artisan key:generate --force
fi

# Ejecutar migraciones
echo "🗄️ Ejecutando migraciones de base de datos..."
php artisan migrate --force

# Cache de configuración para producción
echo "⚡ Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Build completado exitosamente!"
