<?php
/**
 * Realtime API (voz): interfaz unificada con function calling.
 * Recibe la SDP de oferta WebRTC del navegador, la envía a OpenAI /v1/realtime/calls
 * con la API key, instrucciones MOTUS y tools (crear solicitud, agregar vehículos).
 * Devuelve la SDP de respuesta.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/chatbot.php';

$apiKey = defined('CHATBOT_OPENAI_API_KEY') ? CHATBOT_OPENAI_API_KEY : '';
if ($apiKey === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Realtime no configurado (OPENAI_API_KEY)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$sdp = file_get_contents('php://input');
if ($sdp === false || trim($sdp) === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'SDP requerida']);
    exit;
}

$sessionConfig = [
    'type' => 'realtime',
    'model' => 'gpt-realtime',
    'instructions' => getRealtimeInstructions(),
    'tools' => getRealtimeTools(),
    'tool_choice' => 'auto',
    'audio' => [
        'output' => [
            'voice' => 'alloy'
        ]
    ]
];

if (!function_exists('curl_init')) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL no disponible']);
    exit;
}

$post = [
    'sdp' => $sdp,
    'session' => json_encode($sessionConfig)
];

$ch = curl_init('https://api.openai.com/v1/realtime/calls');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey
    ]
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err !== '' || $response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No se pudo conectar con el servicio de voz']);
    exit;
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    $decoded = json_decode($response, true);
    echo json_encode(['error' => $decoded['error']['message'] ?? $response]);
    exit;
}

header('Content-Type: application/sdp');
echo $response;

function getRealtimeInstructions(): string {
    return <<<TEXT
Eres el asistente de voz de MOTUS, el sistema de Solicitudes de Crédito de Motus/AutoMarket. Hablas SIEMPRE en español, de forma clara y breve.

Puedes crear solicitudes de crédito por voz. Reglas obligatorias:

1. Recoge los datos obligatorios: nombre del cliente, cédula, perfil financiero (Asalariado, Jubilado o Independiente), tipo de persona (Natural o Jurídica). Si el usuario menciona vehículos (marca, modelo, año, precio), anótalos.

2. Si falta algún dato obligatorio, pídelo al usuario antes de continuar. NO inventes datos.

3. ANTES de ejecutar create_credit_request, SIEMPRE confirma en voz alta: resume los datos (nombre, cédula, perfil, y vehículos si aplica) y pregunta "¿Confirmas que creo la solicitud con estos datos?" o similar. Solo si el usuario confirma (sí, correcto, adelante, etc.), llama a la herramienta create_credit_request.

4. Después de crear la solicitud, si hay vehículos indicados, llama a add_vehicles_to_request con el solicitud_id que obtuviste y la lista de vehículos.

5. NO digas que creaste la solicitud si no ejecutaste la herramienta. Solo informa el resultado real que te devuelve la herramienta (éxito con ID o error).

6. Cuando el usuario pregunte por autos disponibles, cuántos hay, cuántos de una marca, o qué marcas hay, USA la herramienta query_autos_disponibles ANTES de responder. Responde en voz alta con los datos que te devuelva (total, cantidad por marca, o listado breve).

7. Para dudas de uso del sistema, adjuntos o Pipedrive, responde de forma concisa.
TEXT;
}

function getRealtimeTools(): array {
    return [
        [
            'type' => 'function',
            'name' => 'query_autos_disponibles',
            'description' => 'Consulta el inventario de autos disponibles. Usar cuando el usuario pregunte qué autos hay, cuántos hay, cuántos hay de una marca, o listado por marca.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'marca' => [
                        'type' => 'string',
                        'description' => 'Filtrar por marca (ej: Toyota, Honda). Opcional; si no se pasa, se devuelve todo el inventario.'
                    ],
                    'solo_cantidad' => [
                        'type' => 'boolean',
                        'description' => 'Si es true, solo devuelve totales (cantidad), sin listado de unidades. Útil para preguntas como "cuántos hay" o "cuántos Toyota hay".'
                    ],
                    'limite' => [
                        'type' => 'integer',
                        'description' => 'Máximo de unidades a listar (por defecto 15). Solo aplica si solo_cantidad es false.'
                    ]
                ],
                'required' => []
            ]
        ],
        [
            'type' => 'function',
            'name' => 'create_credit_request',
            'description' => 'Crea una solicitud de crédito en MOTUS. Usar solo después de confirmar con el usuario.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tipo_persona' => [
                        'type' => 'string',
                        'enum' => ['Natural', 'Juridica'],
                        'description' => 'Tipo de persona del cliente'
                    ],
                    'nombre_cliente' => [
                        'type' => 'string',
                        'description' => 'Nombre completo del cliente'
                    ],
                    'cedula' => [
                        'type' => 'string',
                        'description' => 'Cédula del cliente'
                    ],
                    'perfil_financiero' => [
                        'type' => 'string',
                        'enum' => ['Asalariado', 'Jubilado', 'Independiente'],
                        'description' => 'Perfil financiero del cliente'
                    ]
                ],
                'required' => ['tipo_persona', 'nombre_cliente', 'cedula', 'perfil_financiero']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'add_vehicles_to_request',
            'description' => 'Agrega vehículos a una solicitud existente. Llamar después de create_credit_request si el usuario indicó vehículos.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'solicitud_id' => [
                        'type' => 'integer',
                        'description' => 'ID de la solicitud devuelto por create_credit_request'
                    ],
                    'vehiculos' => [
                        'type' => 'array',
                        'description' => 'Lista de vehículos a agregar',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'marca' => ['type' => 'string'],
                                'modelo' => ['type' => 'string'],
                                'anio' => ['type' => 'integer'],
                                'kilometraje' => ['type' => 'integer'],
                                'precio' => ['type' => 'number'],
                                'abono_porcentaje' => ['type' => 'number'],
                                'abono_monto' => ['type' => 'number']
                            ]
                        ]
                    ]
                ],
                'required' => ['solicitud_id']
            ]
        ]
    ];
}
