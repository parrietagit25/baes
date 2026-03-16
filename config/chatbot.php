<?php
/**
 * Configuración del chatbot (OpenAI).
 * La API Key puede venir de: 1) variable de entorno ya definida,
 * 2) archivo .env en la raíz del proyecto (se carga aquí si existe).
 */
if (!defined('CHATBOT_OPENAI_API_KEY')) {
    // Cargar .env si existe y la key no está en el entorno (PHP no carga .env por defecto)
    if (getenv('OPENAI_API_KEY') === false || getenv('OPENAI_API_KEY') === '') {
        $envFile = __DIR__ . '/../.env';
        if (is_file($envFile) && is_readable($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) continue;
                    if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
                        $key = trim($m[1]);
                        $val = trim($m[2]);
                        if (strpos($val, '"') === 0 && substr($val, -1) === '"') {
                            $val = substr($val, 1, -1);
                        } elseif (strpos($val, "'") === 0 && substr($val, -1) === "'") {
                            $val = substr($val, 1, -1);
                        }
                        if (getenv($key) === false) {
                            putenv("$key=$val");
                            $_ENV[$key] = $val;
                        }
                    }
                }
            }
        }
    }
    $key = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '');
    define('CHATBOT_OPENAI_API_KEY', $key !== false && $key !== null ? trim((string) $key) : '');
}
if (!defined('CHATBOT_MODEL')) {
    define('CHATBOT_MODEL', getenv('CHATBOT_MODEL') ?: 'gpt-4o-mini');
}
