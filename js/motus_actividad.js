/**
 * Beacon de telemetría interna MOTUS (páginas autenticadas).
 * Envía clics relevantes, visibilidad y heartbeat al servidor.
 */
(function () {
    'use strict';
    if (window.__motusActividadBooted) return;
    window.__motusActividadBooted = true;

    var endpoint = 'api/usuario_actividad.php';
    var pagina = (function () {
        try {
            var p = location.pathname.split('/').pop() || '';
            return p.indexOf('.php') >= 0 ? p : 'dashboard.php';
        } catch (e) {
            return 'dashboard.php';
        }
    })();
    var queue = [];
    var flushTimer = null;

    function urlPath() {
        try {
            return (location.pathname || '') + (location.search || '');
        } catch (e) {
            return '/' + pagina;
        }
    }

    function enqueue(evento, detalle, seccion) {
        queue.push({
            evento: evento,
            pagina: pagina,
            seccion: seccion || null,
            detalle: detalle ? String(detalle).slice(0, 480) : null,
            url_path: urlPath().slice(0, 480)
        });
        if (queue.length >= 8) {
            flush();
            return;
        }
        if (!flushTimer) {
            flushTimer = setTimeout(flush, 2500);
        }
    }

    function flush() {
        if (flushTimer) {
            clearTimeout(flushTimer);
            flushTimer = null;
        }
        if (!queue.length) return;
        var batch = queue.splice(0, 25);
        var body = JSON.stringify({ eventos: batch });
        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(endpoint, blob)) return;
            }
        } catch (e) {}
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        } catch (e2) {}
    }

    function labelFromEl(el) {
        if (!el) return '';
        var t = (el.getAttribute('aria-label') || el.getAttribute('title') || el.innerText || el.value || el.id || '').trim();
        t = t.replace(/\s+/g, ' ').slice(0, 120);
        return t;
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var a = t.closest('a.nav-link, a.btn, button, [data-bs-toggle="tab"], .nav-link');
        if (!a) return;
        var det = labelFromEl(a);
        if (!det) return;
        var href = a.getAttribute('href') || '';
        if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
            det = det + ' → ' + href.slice(0, 80);
        }
        enqueue('click', det);
    }, true);

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.tagName !== 'FORM') return;
        var id = f.id || f.getAttribute('name') || 'form';
        enqueue('action', 'submit:' + id);
    }, true);

    document.addEventListener('visibilitychange', function () {
        enqueue('visibility', document.hidden ? 'hidden' : 'visible');
        if (document.hidden) flush();
    });

    setInterval(function () {
        if (!document.hidden) enqueue('heartbeat', 'alive');
    }, 60000);

    window.addEventListener('beforeunload', function () {
        enqueue('visibility', 'unload');
        flush();
    });
})();
