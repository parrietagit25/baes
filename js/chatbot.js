/**
 * Widget de chat: burbuja flotante que envía mensajes a api/chatbot.php
 */
(function () {
    'use strict';

    var bubble = document.getElementById('chatbot-bubble');
    if (!bubble) return;

    var panel = bubble.querySelector('.chatbot-panel');
    var messagesEl = bubble.querySelector('.chatbot-messages');
    var form = bubble.querySelector('.chatbot-form');
    var input = bubble.querySelector('.chatbot-input');
    var toggleBtn = bubble.querySelector('.chatbot-toggle');
    var closeBtn = bubble.querySelector('.chatbot-close');

    function openPanel() {
        bubble.classList.add('chatbot-open');
        if (messagesEl.children.length === 0) {
            appendBotMessage('Hola. Soy el asistente de MOTUS. Puedo ayudarte con el uso del sistema, autos disponibles o dudas sobre solicitudes. ¿En qué puedo ayudarte?', false);
        }
        input.focus();
    }

    function closePanel() {
        bubble.classList.remove('chatbot-open');
    }

    function appendBotMessage(text, isError) {
        var div = document.createElement('div');
        div.className = 'chatbot-msg bot' + (isError ? ' error' : '');
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendUserMessage(text) {
        var div = document.createElement('div');
        div.className = 'chatbot-msg user';
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setTyping(show) {
        var existing = messagesEl.querySelector('.chatbot-msg .chatbot-typing');
        if (existing) existing.closest('.chatbot-msg').remove();
        if (show) {
            var div = document.createElement('div');
            div.className = 'chatbot-msg bot';
            div.innerHTML = '<span class="chatbot-typing">Escribiendo...</span>';
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    toggleBtn.addEventListener('click', function () {
        if (bubble.classList.contains('chatbot-open')) {
            closePanel();
        } else {
            openPanel();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closePanel);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = (input.value || '').trim();
        if (!text) return;

        appendUserMessage(text);
        input.value = '';
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        setTyping(true);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/chatbot.php');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.timeout = 45000;
        xhr.onload = function () {
            setTyping(false);
            if (submitBtn) submitBtn.disabled = false;
            if (xhr.status !== 200) {
                appendBotMessage('El servidor respondió con error (código ' + xhr.status + '). Revisa los logs del servidor.', true);
                return;
            }
            var res = {};
            try {
                res = JSON.parse(xhr.responseText || '{}');
            } catch (err) {
                appendBotMessage('Respuesta del servidor no válida. Intenta de nuevo.', true);
                return;
            }
            if (res.success && res.reply) {
                appendBotMessage(res.reply, false);
            } else {
                appendBotMessage(res.message || 'No se pudo obtener respuesta. Intenta de nuevo.', true);
            }
        };
        xhr.onerror = function () {
            setTyping(false);
            if (submitBtn) submitBtn.disabled = false;
            appendBotMessage('No se pudo conectar con el servidor. Verifica tu red o intenta más tarde.', true);
        };
        xhr.ontimeout = function () {
            setTyping(false);
            if (submitBtn) submitBtn.disabled = false;
            appendBotMessage('Tiempo de espera agotado. El asistente tarda demasiado; intenta de nuevo.', true);
        };
        xhr.send(JSON.stringify({ message: text }));
    });
})();
