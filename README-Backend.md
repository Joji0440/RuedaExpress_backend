# Mecánica App - Backend API

Este es el backend de la aplicación de mecánica desarrollado con Laravel. Proporciona una API RESTful para gestionar usuarios, vehículos, perfiles de mecánicos y solicitudes de servicio.

## Características

- API RESTful desarrollada con Laravel
- Autenticación con Laravel Sanctum
- Gestión de roles y permisos con Spatie Permission
- Base de datos PostgreSQL
- Gestión de perfiles de clientes y mecánicos
- Sistema de solicitudes de servicio
- Cálculo de distancias para encontrar mecánicos cercanos

## Modelos principales

- **User**: Gestión de usuarios del sistema
- **ClientProfile**: Perfiles de clientes
- **MechanicProfile**: Perfiles de mecánicos con disponibilidad
- **Vehicle**: Vehículos registrados por los clientes
- **ServiceRequest**: Solicitudes de servicio mecánico

## Requisitos del sistema

- PHP >= 8.1
- Composer
- PostgreSQL
- Node.js (para asset compilation)

## Instalación

1. Clonar el repositorio:
```bash
git clone <repository-url>
cd mecanica-backend
```

2. Instalar dependencias de PHP:
```bash
composer install
```

3. Copiar el archivo de configuración:
```bash
cp .env.example .env
```

4. Configurar la base de datos en el archivo `.env`:
```
DB_CONNECTION=pgsql
DB_HOST=your_host
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. Generar la clave de la aplicación:
```bash
php artisan key:generate
```

6. Ejecutar las migraciones:
```bash
php artisan migrate
```

7. Ejecutar los seeders (opcional):
```bash
php artisan db:seed
```

8. Iniciar el servidor de desarrollo:
```bash
php artisan serve
```

## Configuración de la API

La API estará disponible en `http://localhost:8000/api`

### Endpoints principales

- `POST /api/register` - Registro de usuarios
- `POST /api/login` - Inicio de sesión
- `GET /api/user` - Obtener información del usuario autenticado
- `GET /api/mechanics` - Listar mecánicos disponibles
- `POST /api/service-requests` - Crear solicitud de servicio
- `GET /api/vehicles` - Listar vehículos del usuario

## Servicios

### DistanceCalculator
Servicio para calcular distancias entre ubicaciones geográficas.

### UserService
Servicio para la gestión de usuarios y sus perfiles.

## Testing

Ejecutar las pruebas:
```bash
php artisan test
```

## Estructura del proyecto

```
app/
├── Console/Commands/     # Comandos de Artisan
├── DTO/                  # Data Transfer Objects
├── Http/
│   ├── Controllers/      # Controladores de la API
│   └── Middleware/       # Middleware personalizado
├── Models/               # Modelos Eloquent
├── Providers/            # Service Providers
├── Services/             # Servicios de la aplicación
└── Traits/               # Traits reutilizables
```

## Contribuir

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear un Pull Request

## Licencia

Este proyecto está bajo la licencia MIT.
