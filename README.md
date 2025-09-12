# Sistema de Solicitud de Crédito

Sistema web completo para la gestión de solicitudes de crédito con integración Pipedrive, desarrollado en PHP, MySQL y Bootstrap.

## Características

- **Sistema de Login Seguro**: Autenticación con hash de contraseñas
- **Gestión de Usuarios**: CRUD completo de usuarios con roles
- **Solicitudes de Crédito**: Gestión completa del proceso de crédito
- **Integración Pipedrive**: Sincronización de leads (API + CSV)
- **Muro de Tiempo**: Notas y actualizaciones en tiempo real
- **Interfaz Moderna**: Diseño responsive con Bootstrap 5
- **Modales**: Todas las operaciones se realizan sin salir de la pantalla
- **DataTables**: Tablas con paginación, búsqueda y ordenamiento
- **Validaciones**: Validación en tiempo real de datos
- **Seguridad**: Control de acceso basado en roles

## Requisitos del Sistema

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- Extensiones PHP: PDO, PDO_MySQL, session

## Instalación

### 1. Configuración de la Base de Datos

1. Crear una base de datos MySQL llamada `sistema_usuarios`
2. Importar el archivo `database/schema.sql` en tu base de datos
3. Modificar `config/database.php` con tus credenciales de base de datos

### 2. Configuración del Servidor

1. Colocar todos los archivos en tu directorio web
2. Asegurarte de que el servidor web tenga permisos de lectura
3. Configurar el servidor para usar PHP

### 3. Usuario por Defecto

El sistema incluye un usuario administrador por defecto:
- **Email**: admin@sistema.com
- **Contraseña**: admin123

## Estructura del Proyecto

```
farov2/
├── api/
│   ├── usuarios.php      # API para gestión de usuarios
│   └── roles.php         # API para gestión de roles
├── config/
│   └── database.php      # Configuración de base de datos
├── database/
│   └── schema.sql        # Esquema de la base de datos
├── js/
│   └── dashboard.js      # Funcionalidades JavaScript del dashboard
├── index.php             # Página de login
├── dashboard.php         # Dashboard principal
├── logout.php            # Script de logout
└── README.md             # Este archivo
```

## Uso del Sistema

### Login
1. Acceder a `index.php`
2. Usar las credenciales del usuario administrador
3. El sistema redirigirá al dashboard

### Gestión de Usuarios
- **Ver Usuarios**: Lista completa en la tabla principal
- **Crear Usuario**: Botón "Registrar Usuario" (solo administradores)
- **Editar Usuario**: Botón de editar en la columna acciones
- **Eliminar Usuario**: Botón de eliminar en la columna acciones

### Gestión de Roles
- **Ver Roles**: Enlace "Gestión de Roles" en el sidebar
- **Crear Rol**: Botón "Nuevo Rol" en el modal de roles
- **Editar Rol**: Funcionalidad disponible en la API
- **Eliminar Rol**: Solo roles personalizados (no del sistema)

## Funcionalidades del Sistema

### Sistema de Roles
- **ROLE_ADMIN**: Acceso completo al sistema
- **ROLE_GESTOR**: Gestor de crédito (crear solicitudes)
- **ROLE_BANCO**: Analista bancario (aprobar/rechazar)
- **ROLE_SUPERVISOR**: Acceso a reportes y gestión
- **ROLE_USER**: Usuario estándar
- **ROLE_AM**: Asistente de Marketing
- **ROLE_VENDEDOR**: Vendedor del sistema
- **ROLE_COBRADOR**: Cobrador del sistema

## 🔗 Integración con Pipedrive

El sistema incluye integración completa con Pipedrive para sincronización de leads:

### Estado Actual de la Integración
- **Conexión básica**: ✅ Funcionando
- **API de leads**: ⚠️ Requiere plan Professional/Premium/Ultimate
- **Importación CSV**: ✅ Completamente funcional
- **Sincronización automática**: Disponible con plan actualizado

### Cómo Usar la Integración

#### Opción 1: Importación CSV (Recomendada - Funciona Ahora)
1. Ve a "Importar CSV" en el menú
2. Descarga la plantilla CSV
3. Exporta leads desde Pipedrive en formato CSV
4. Sube el archivo al sistema
5. Los leads se importarán automáticamente

#### Opción 2: Sincronización Automática (Requiere Plan Actualizado)
1. Actualiza tu plan de Pipedrive a Professional/Premium/Ultimate
2. La sincronización automática funcionará inmediatamente
3. Los leads se importarán automáticamente sin intervención manual

### Campos de Solicitud de Crédito

#### Campos del Gestor
- **Datos Generales**: Nombre, cédula, edad, género, dirección
- **Perfil Financiero**: Tipo (Asalariado/Jubilado/Independiente), ingreso, empresa
- **Datos del Auto**: Marca, modelo, año, kilometraje, precio
- **Comentarios**: Análisis del perfil del cliente

#### Campos del Banco
- **Respuesta**: Aprobado/Pre Aprobado/Rechazado
- **Términos**: Letra, plazo, abono, promoción
- **Comentarios**: Condiciones y requerimientos del banco

#### Campos del Cliente
- **Respuesta**: Acepta/Rechaza
- **Motivo**: Razón de aceptación o rechazo
- **Fechas**: Envío proforma, firma, póliza, carta promesa

### Campos de Usuario
- Información personal (nombre, apellido, email)
- Información de contacto (teléfono, país)
- Información laboral (cargo, ID cobrador, ID vendedor)
- Estado del usuario (activo/inactivo)
- Primer acceso (requiere cambio de contraseña)

### Seguridad
- Contraseñas hasheadas con `password_hash()`
- Validación de sesiones
- Control de acceso basado en roles
- Prevención de SQL injection con PDO
- Validación de datos de entrada

## Personalización

### Agregar Nuevos Campos
1. Modificar la tabla `usuarios` en `database/schema.sql`
2. Actualizar el formulario en `dashboard.php`
3. Modificar las APIs correspondientes
4. Actualizar el JavaScript para manejar los nuevos campos

### Agregar Nuevos Roles
1. Insertar en la tabla `roles`
2. Asignar a usuarios según sea necesario
3. El sistema automáticamente los mostrará en los formularios

### Cambiar Estilos
- Modificar el CSS en `dashboard.php`
- Personalizar colores y temas en las variables CSS
- Agregar clases personalizadas de Bootstrap

## Solución de Problemas

### Error de Conexión a Base de Datos
- Verificar credenciales en `config/database.php`
- Asegurar que MySQL esté ejecutándose
- Verificar que la base de datos exista

### Error de Permisos
- Verificar que el usuario de la base de datos tenga permisos
- Asegurar permisos de lectura/escritura en el servidor web

### Problemas de Sesión
- Verificar configuración de PHP para sesiones
- Asegurar que las cookies estén habilitadas

## Tecnologías Utilizadas

- **Backend**: PHP 7.4+, PDO, MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3.0
- **Librerías JS**: jQuery 3.7.0, DataTables 1.13.7
- **Iconos**: Font Awesome 6.0.0

## Soporte

Para soporte técnico o preguntas sobre el sistema, contactar al equipo de desarrollo.

## Licencia

Este proyecto es de uso interno y no está destinado para distribución comercial.
