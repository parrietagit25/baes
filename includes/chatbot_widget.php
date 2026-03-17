<?php
/**
 * Incluir en las páginas donde quieras el chatbot (ej. antes de </body>).
 * Requiere que el usuario esté logueado; si no, no se muestra la burbuja.
 */
if (empty($_SESSION['user_id'])) {
    return;
}
$chatbotCss = 'css/chatbot.css';
$chatbotJs = 'js/chatbot.js';
$v = file_exists(__DIR__ . '/../' . $chatbotCss) ? filemtime(__DIR__ . '/../' . $chatbotCss) : time();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($chatbotCss); ?>?v=<?php echo (int) $v; ?>">
<div id="chatbot-bubble">
    <button type="button" class="chatbot-toggle" aria-label="Abrir asistente">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
    </button>
    <div class="chatbot-panel">
        <div class="chatbot-header">
            <span>Asistente MOTUS</span>
            <button type="button" class="chatbot-voice-btn" aria-label="Llamada de voz" title="Llamar por voz"><svg class="chatbot-voice-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></button>
            <button type="button" class="chatbot-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="chatbot-voice-bar d-none">
            <span class="chatbot-voice-status">Conectando...</span>
            <button type="button" class="chatbot-voice-hangup" aria-label="Colgar">Colgar</button>
        </div>
        <div class="chatbot-messages"></div>
        <div class="chatbot-input-wrap">
            <form class="chatbot-form" action="#" method="post">
                <input type="text" class="chatbot-input" placeholder="Escribe tu pregunta..." autocomplete="off" maxlength="2000">
                <button type="submit">Enviar</button>
            </form>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars($chatbotJs); ?>?v=<?php echo file_exists(__DIR__ . '/../' . $chatbotJs) ? filemtime(__DIR__ . '/../' . $chatbotJs) : time(); ?>"></script>
