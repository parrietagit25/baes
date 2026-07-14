<?php
/**
 * Página pública: el cliente sube adjuntos con un token (24 h, reutilizable).
 * Enlace: .../financiamiento/solicitar_adjuntos.php?t=TOKEN
 */
require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    motus_emitir_mantenimiento_html();
    exit();
}

$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
$apiBase = '../api/financiamiento_adjuntos_public.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Subir adjuntos - AutoMarket</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #0b1220; color: #eaf0ff; min-height: 100vh; padding: 20px; }
    .card-adj { background: #0f1b33; border: 1px solid rgba(255,255,255,.12); border-radius: 16px; max-width: 640px; margin: 0 auto; padding: 24px; }
    .list-adj .item { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
    .text-muted-soft { color: rgba(234,240,255,.65) !important; }
  </style>
</head>
<body>
  <div class="card-adj">
    <h1 class="h5 mb-2"><i class="fas fa-paperclip me-2"></i>Subir documentos</h1>
    <p id="subtitulo" class="text-muted-soft small mb-3">Validando enlace…</p>
    <div id="alerta" class="alert d-none" role="alert"></div>

    <div id="bloqueOk" class="d-none">
      <p class="small mb-3">Puede subir archivos (PDF, imágenes, Word/Excel; máx. 10 MB c/u). El enlace es válido hasta la fecha indicada; al volver a abrirlo verá lo que ya tiene cargado.</p>

      <h2 class="h6">Adjuntos ya cargados</h2>
      <div id="listaAdjuntos" class="list-adj mb-3">
        <p class="text-muted-soft small mb-0">Ninguno aún.</p>
      </div>

      <form id="formAdjuntos" class="mb-2">
        <label class="form-label small" for="inputFiles">Seleccionar archivos</label>
        <input type="file" class="form-control form-control-sm mb-3" id="inputFiles" name="adjuntos[]" multiple
               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,image/*,application/pdf">
        <button type="submit" class="btn btn-success" id="btnSubir" disabled>
          <i class="fas fa-cloud-upload-alt me-1"></i>Subir adjuntos
        </button>
      </form>
      <p id="hintCaduca" class="small text-muted-soft mb-0"></p>
    </div>
  </div>

  <script>
    (function () {
      var TOKEN = <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      var API = <?php echo json_encode($apiBase, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

      function showAlert(kind, msg) {
        var el = document.getElementById('alerta');
        el.className = 'alert ' + (kind === 'ok' ? 'alert-success' : 'alert-danger');
        el.textContent = msg;
        el.classList.remove('d-none');
      }

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
      }

      function fmtSize(n) {
        if (n == null || n === '') return '';
        n = Number(n);
        if (!isFinite(n) || n <= 0) return '';
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
      }

      function renderAdjuntos(list) {
        var box = document.getElementById('listaAdjuntos');
        if (!list || !list.length) {
          box.innerHTML = '<p class="text-muted-soft small mb-0">Ninguno aún.</p>';
          return;
        }
        var html = '';
        list.forEach(function (a) {
          var meta = [a.tipo_archivo, fmtSize(a.tamano_archivo), a.fecha_subida].filter(Boolean).join(' · ');
          html += '<div class="item">' +
            '<div><i class="fas fa-file me-2"></i><strong>' + esc(a.nombre_original || ('Adjunto #' + a.id)) + '</strong></div>' +
            (meta ? '<div class="small text-muted-soft mt-1">' + esc(meta) + '</div>' : '') +
            '</div>';
        });
        box.innerHTML = html;
      }

      function aplicarDatos(data) {
        var nombre = (data && data.cliente_nombre) ? data.cliente_nombre : 'cliente';
        document.getElementById('subtitulo').textContent = 'Hola, ' + nombre;
        document.getElementById('hintCaduca').textContent = data.expires_at
          ? ('Enlace válido hasta: ' + data.expires_at)
          : '';
        renderAdjuntos(data.adjuntos || []);
        document.getElementById('bloqueOk').classList.remove('d-none');
      }

      if (!TOKEN) {
        showAlert('err', 'Enlace incompleto.');
        document.getElementById('subtitulo').textContent = 'No se pudo abrir el enlace.';
        return;
      }

      fetch(API + '?t=' + encodeURIComponent(TOKEN))
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.j || !res.j.success) {
            showAlert('err', (res.j && res.j.message) ? res.j.message : 'Enlace no válido.');
            document.getElementById('subtitulo').textContent = 'Enlace no disponible.';
            return;
          }
          aplicarDatos(res.j.data || {});
        })
        .catch(function () {
          showAlert('err', 'Error de conexión.');
          document.getElementById('subtitulo').textContent = 'No se pudo validar el enlace.';
        });

      var input = document.getElementById('inputFiles');
      var btn = document.getElementById('btnSubir');
      input.addEventListener('change', function () {
        btn.disabled = !(input.files && input.files.length);
      });

      document.getElementById('formAdjuntos').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!input.files || !input.files.length) return;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('t', TOKEN);
        for (var i = 0; i < input.files.length; i++) {
          fd.append('adjuntos[]', input.files[i]);
        }
        fetch(API, { method: 'POST', body: fd })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            if (!res.j || !res.j.success) {
              showAlert('err', (res.j && res.j.message) ? res.j.message : 'No se pudo subir.');
              if (res.j && res.j.data) aplicarDatos(res.j.data);
              btn.disabled = !(input.files && input.files.length);
              return;
            }
            showAlert('ok', res.j.message || 'Archivos subidos.');
            if (res.j.data) aplicarDatos(res.j.data);
            input.value = '';
            btn.disabled = true;
          })
          .catch(function () {
            showAlert('err', 'Error de conexión al subir.');
            btn.disabled = !(input.files && input.files.length);
          });
      });
    })();
  </script>
</body>
</html>
