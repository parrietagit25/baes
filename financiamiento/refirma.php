<?php
/**
 * Página pública: el cliente vuelve a firmar usando un token temporal (30 min, un solo uso).
 * Enlace: .../financiamiento/refirma.php?t=TOKEN
 */
require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    motus_emitir_mantenimiento_html();
    exit();
}

$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
$apiBase = '../api/financiamiento_refirma_public.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Refirmar solicitud - AutoMarket</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #0b1220; color: #eaf0ff; min-height: 100vh; padding: 20px; }
    .card-ref { background: #0f1b33; border: 1px solid rgba(255,255,255,.12); border-radius: 16px; max-width: 560px; margin: 0 auto; padding: 24px; }
    .signature-wrap { border: 2px solid rgba(255,255,255,.2); border-radius: 12px; background: rgba(0,0,0,.2); touch-action: none; overflow: hidden; }
    #firmaCanvas { display: block; width: 100%; height: 180px; cursor: crosshair; border-radius: 10px; touch-action: none; }
  </style>
</head>
<body>
  <div class="card-ref">
    <h1 class="h5 mb-2">Volver a firmar</h1>
    <p id="subtitulo" class="text-secondary small mb-3">Validando enlace…</p>
    <div id="alerta" class="alert d-none" role="alert"></div>
    <div id="bloqueFirma" class="d-none">
      <p class="small mb-2">Dibuje su firma en el recuadro (dedo o ratón).</p>
      <div class="signature-wrap mb-2">
        <canvas id="firmaCanvas" width="500" height="180"></canvas>
      </div>
      <button type="button" class="btn btn-outline-light btn-sm mb-3" id="btnLimpiar">Limpiar</button>
      <div>
        <button type="button" class="btn btn-success" id="btnEnviar" disabled>Guardar firma</button>
      </div>
    </div>
  </div>
  <input type="hidden" id="firmaData" />
  <script>
    (function(){
      var TOKEN = <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      var API = <?php echo json_encode($apiBase, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

      function showAlert(kind, msg) {
        var el = document.getElementById('alerta');
        el.className = 'alert ' + (kind === 'ok' ? 'alert-success' : 'alert-danger');
        el.textContent = msg;
        el.classList.remove('d-none');
      }

      function setupCanvas(canvas, dataInput) {
        if (!canvas || !canvas.getContext) return;
        var ctx = canvas.getContext('2d');
        var drawing = false;
        function pos(e) {
          var r = canvas.getBoundingClientRect();
          var x, y;
          if (e.touches && e.touches[0]) {
            x = e.touches[0].clientX - r.left;
            y = e.touches[0].clientY - r.top;
          } else {
            x = e.clientX - r.left;
            y = e.clientY - r.top;
          }
          var sx = canvas.width / r.width, sy = canvas.height / r.height;
          return { x: x * sx, y: y * sy };
        }
        function start(e) {
          drawing = true;
          var p = pos(e);
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
        }
        function move(e) {
          if (!drawing) return;
          if (e.cancelable && e.type === 'touchmove') e.preventDefault();
          var p = pos(e);
          ctx.strokeStyle = '#1e293b';
          ctx.lineWidth = 2.2;
          ctx.lineCap = 'round';
          ctx.lineJoin = 'round';
          ctx.lineTo(p.x, p.y);
          ctx.stroke();
        }
        function end() {
          drawing = false;
          if (dataInput) {
            dataInput.value = canvas.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
            document.getElementById('btnEnviar').disabled = !dataInput.value || dataInput.value.length < 80;
          }
        }
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, { passive: true });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: true });
      }

      if (!TOKEN || !/^[a-f0-9]{64}$/i.test(TOKEN)) {
        document.getElementById('subtitulo').textContent = 'Falta el enlace o no es válido.';
        showAlert('err', 'Abra el enlace completo que recibió por correo.');
        return;
      }

      fetch(API + '?t=' + encodeURIComponent(TOKEN))
        .then(function(r){ return r.json(); })
        .then(function(res) {
          if (!res.success) {
            document.getElementById('subtitulo').textContent = res.message || 'Enlace no válido.';
            showAlert('err', res.message || 'No se pudo validar el enlace.');
            return;
          }
          var d = res.data || {};
          var nombre = d.cliente_nombre || 'Cliente';
          document.getElementById('subtitulo').textContent = 'Hola, ' + nombre + '. Complete su firma y pulse Guardar.';
          document.getElementById('bloqueFirma').classList.remove('d-none');
          var canvas = document.getElementById('firmaCanvas');
          var firmaInput = document.getElementById('firmaData');
          setupCanvas(canvas, firmaInput);
          document.getElementById('btnLimpiar').onclick = function() {
            var c = document.getElementById('firmaCanvas');
            if (c) { c.getContext('2d').clearRect(0, 0, c.width, c.height); }
            firmaInput.value = '';
            document.getElementById('btnEnviar').disabled = true;
          };
          document.getElementById('btnEnviar').onclick = function() {
            var btn = this;
            btn.disabled = true;
            fetch(API, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ token: TOKEN, firma: firmaInput.value })
            })
            .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, j: j }; }); })
            .then(function(x) {
              if (x.j && x.j.success) {
                showAlert('ok', x.j.message || 'Firma guardada. Ya puede cerrar esta ventana.');
                document.getElementById('bloqueFirma').classList.add('d-none');
              } else {
                showAlert('err', (x.j && x.j.message) ? x.j.message : 'No se pudo guardar.');
                btn.disabled = false;
              }
            })
            .catch(function() {
              showAlert('err', 'Error de red. Intente de nuevo.');
              btn.disabled = false;
            });
          };
        })
        .catch(function() {
          document.getElementById('subtitulo').textContent = 'Error de conexión.';
          showAlert('err', 'No se pudo contactar al servidor.');
        });
    })();
  </script>
</body>
</html>
