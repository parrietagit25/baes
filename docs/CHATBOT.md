# Chatbot integrado (Asistente MOTUS)

Asistente en burbuja que usa la API de OpenAI, consulta la base de datos (inventario, total de autos) cuando el usuario pregunta y explica el uso de la app. El nombre del asistente es **MOTUS**.

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

La burbuja aparece en **todas las páginas internas** (cuando el usuario está logueado), **excepto en el login**: Dashboard, Solicitudes, Sol Financiamiento, Usuarios, Roles, Bancos, Pipedrive, Reportes. No se muestra en `index.php` ni en la pantalla de login. Solo se renderiza si existe `$_SESSION['user_id']`.

## Inventario

Si el usuario pregunta por autos o inventario, el backend consulta la tabla `Automarket_Invs_web_temp` (misma BD que usa el resto de BAES) y envía un resumen al prompt de OpenAI. Si esa tabla no existe en tu BD, el bot responderá que puede ver el listado en "Autos disponibles" dentro de la solicitud.

## Voz (Realtime) y creación de solicitudes por voz

- **api/realtime_session.php** – Crea la sesión WebRTC con OpenAI Realtime API: instrucciones, voz y **tools** (function calling).
- **api/realtime_execute_tool.php** – Ejecuta las herramientas cuando el modelo las llama: `create_credit_request` (POST a api/solicitudes.php) y `add_vehicles_to_request` (POST a api/vehiculos_solicitud.php). Requiere sesión y rol gestor/admin para crear solicitudes.
- **js/chatbot.js** – En llamada de voz, escucha el data channel `oai-events`; si recibe `response.done` con `function_call`, llama a `realtime_execute_tool.php`, envía el resultado como `function_call_output` y dispara `response.create`.

Flujo por voz: el usuario puede decir por ejemplo *"Crear solicitud para Ana Gómez, cédula 8-999-111, independiente, con un Toyota Raize 2023"*. El asistente pide datos faltantes si aplica, **pide confirmación** antes de ejecutar, crea la solicitud y los vehículos usando los endpoints existentes y responde con el resultado (éxito o error).

## Cloudflare

El endpoint es `POST /api/chatbot.php` con JSON. Si ya tienes una regla que permite POST a `/api/`, el chatbot no debería ser bloqueado. Para voz se usan también `api/realtime_session.php` y `api/realtime_execute_tool.php`.
