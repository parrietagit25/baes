# Chatbot integrado (Opción B)

Asistente en burbuja que usa la API de OpenAI, consulta la base de datos cuando el usuario pregunta por inventario y explica el uso de la app.

## Archivos

- **api/chatbot.php** – Endpoint POST; recibe `{ "message": "..." }`, llama a OpenAI y opcionalmente a la BD.
- **config/chatbot.php** – Lee `OPENAI_API_KEY` y `CHATBOT_MODEL` del entorno.
- **css/chatbot.css** – Estilos del widget.
- **js/chatbot.js** – Lógica de la burbuja y envío de mensajes.
- **includes/chatbot_widget.php** – Fragmento para incluir en las páginas (solo se muestra si hay sesión).

## Configuración

1. **API Key de OpenAI**  
   Crear una en [OpenAI API Keys](https://platform.openai.com/api-keys) y definir la variable de entorno:

   ```bash
   export OPENAI_API_KEY=sk-...
   ```

   Si usas `.env`, añade:

   ```env
   OPENAI_API_KEY=sk-...
   ```

   En PHP, si no cargas `.env` automáticamente, puedes definir la key en `config/chatbot.php` (solo para pruebas; en producción usar variables del servidor):

   ```php
   define('CHATBOT_OPENAI_API_KEY', 'sk-...');
   ```

2. **Modelo** (opcional)  
   Por defecto se usa `gpt-4o-mini`. Para cambiar:

   ```env
   CHATBOT_MODEL=gpt-4o
   ```

## Dónde se muestra

El widget está incluido en **solicitudes.php**. Para añadirlo a más páginas, incluye antes de `</body>`:

```php
<?php include __DIR__ . '/includes/chatbot_widget.php'; ?>
```

Solo se renderiza si el usuario está logueado (`$_SESSION['user_id']`).

## Inventario

Si el usuario pregunta por autos o inventario, el backend consulta la tabla `Automarket_Invs_web_temp` (misma BD que usa el resto de BAES) y envía un resumen al prompt de OpenAI. Si esa tabla no existe en tu BD, el bot responderá que puede ver el listado en "Autos disponibles" dentro de la solicitud.

## Cloudflare

El endpoint es `POST /api/chatbot.php` con JSON. Si ya tienes una regla que permite POST a `/api/`, el chatbot no debería ser bloqueado.
