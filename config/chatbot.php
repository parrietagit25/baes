<?php
/**
 * Configuración del chatbot (OpenAI).
 * La API Key puede venir de variable de entorno OPENAI_API_KEY o definirse aquí.
 * En producción: usar .env o variables del servidor, nunca subir la key al repo.
 */
if (!defined('CHATBOT_OPENAI_API_KEY')) {
    define('CHATBOT_OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}
if (!defined('CHATBOT_MODEL')) {
    define('CHATBOT_MODEL', getenv('CHATBOT_MODEL') ?: 'gpt-4o-mini');
}
