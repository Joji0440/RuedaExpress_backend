# 🚀 RuedaExpress Backend - Deploy en Render.com

> Backend API Laravel para la plataforma RuedaExpress

## 📋 Configuración en Render.com

### 1. **Conectar Repositorio**
- Conecta este repositorio (carpeta `Mecanica/`) a Render
- Tipo de servicio: **Web Service**
- Branch: `main`

### 2. **Configuración del Servicio**
```
Name: ruedaexpress-backend
Environment: Node
Build Command: chmod +x build.sh && ./build.sh
Start Command: vendor/bin/heroku-php-apache2 public/
```

### 3. **Variables de Entorno Requeridas**
Configura estas variables en el panel de Render:

```bash
# Aplicación
APP_NAME=RuedaExpress
APP_ENV=production
APP_DEBUG=false
APP_URL=https://TU-APP.onrender.com

# Base de datos Supabase
DB_CONNECTION=pgsql
DB_HOST=aws-0-us-east-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.baascsicbxenjmfefbbc
DB_PASSWORD=lizzardi042003@

# Configuración básica
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_LEVEL=error

# CORS (se actualizará con la URL de Vercel)
FRONTEND_URL=https://tu-frontend.vercel.app
```

### 4. **Después del Deploy**
1. Render generará una URL como: `https://ruedaexpress-backend.onrender.com`
2. Probar el endpoint: `https://tu-app.onrender.com/api/health`
3. Usar esta URL para configurar el frontend en Vercel

## 🔧 Comandos Útiles

### Ejecutar localmente:
```bash
composer install
php artisan serve
```

### Probar API:
```bash
curl https://tu-app.onrender.com/api/health
```

## 📝 Notas
- La primera build puede tomar 5-10 minutos
- Render ejecutará automáticamente las migraciones
- Los logs están disponibles en el panel de Render
