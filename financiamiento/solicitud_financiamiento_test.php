<?php
require_once __DIR__ . '/config.php';
$apiUrlConfig = defined('FINANCIAMIENTO_API_URL') && FINANCIAMIENTO_API_URL !== '' ? FINANCIAMIENTO_API_URL : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Formulario Publico Test (Espejo)</title>
  <style>
    :root {
      --bg: #0f172a;
      --panel: #111827;
      --line: #334155;
      --text: #e5e7eb;
      --muted: #94a3b8;
      --ok: #16a34a;
      --warn: #f59e0b;
      --danger: #dc2626;
      --primary: #2563eb;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.4;
    }
    .wrap {
      max-width: 1100px;
      margin: 20px auto;
      padding: 0 16px 24px;
    }
    .card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 14px;
    }
    h1 { margin: 0 0 10px; font-size: 1.35rem; }
    h2 { margin: 0 0 10px; font-size: 1.1rem; color: #bfdbfe; }
    .muted { color: var(--muted); }
    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    .full { grid-column: 1 / -1; }
    label {
      display: block;
      font-size: .88rem;
      color: #cbd5e1;
      margin-bottom: 4px;
    }
    input, textarea, button {
      width: 100%;
      border-radius: 8px;
      border: 1px solid var(--line);
      padding: 10px;
      background: #0b1220;
      color: var(--text);
      font: inherit;
    }
    textarea {
      min-height: 300px;
      resize: vertical;
      font-family: Consolas, monospace;
      font-size: 12px;
    }
    .row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .row > button {
      width: auto;
      min-width: 170px;
      cursor: pointer;
    }
    .btn-primary { background: var(--primary); border-color: var(--primary); }
    .btn-ok { background: var(--ok); border-color: var(--ok); }
    .btn-warn { background: var(--warn); border-color: var(--warn); color: #111827; font-weight: 700; }
    .state {
      padding: 10px;
      border-radius: 8px;
      margin-top: 10px;
      border: 1px solid var(--line);
      background: #020617;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .state.ok { border-color: rgba(22,163,74,.6); }
    .state.err { border-color: rgba(220,38,38,.6); }
    code { color: #93c5fd; }
    @media (max-width: 800px) {
      .grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Formulario Publico Test (Espejo)</h1>
      <p class="muted">
        Este formulario es solo para pruebas y envia al mismo endpoint publico sin tocar el flujo actual.
      </p>
      <p class="muted">
        Flujo recomendado: autollenar -> adjuntar archivos -> enviar -> esperar correo y validar adjuntos.
      </p>
    </div>

    <div class="card">
      <h2>Configuracion de envio</h2>
      <div class="grid">
        <div class="full">
          <label for="apiUrl">Endpoint API</label>
          <input id="apiUrl" value="" />
        </div>
        <div>
          <label for="emailVendedor">Email vendedor (token)</label>
          <input id="emailVendedor" type="email" placeholder="vendedor@dominio.com" />
        </div>
        <div>
          <label for="emailCliente">Email cliente destino</label>
          <input id="emailCliente" type="email" placeholder="cliente@dominio.com" />
        </div>
        <div class="full">
          <label for="adjuntosExtra">Adjuntos extra (para probar correo con archivos)</label>
          <input id="adjuntosExtra" type="file" multiple />
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Payload JSON de prueba</h2>
      <p class="muted">
        Puedes editar cualquier campo. Si quieres restaurar datos completos, usa "Autollenar datos de prueba".
      </p>
      <textarea id="payloadJson"></textarea>
      <div class="row" style="margin-top:10px;">
        <button type="button" id="btnAutoFill" class="btn-warn">Autollenar datos de prueba</button>
        <button type="button" id="btnValidar">Validar JSON</button>
        <button type="button" id="btnEnviar" class="btn-primary">Enviar solicitud test</button>
      </div>
      <div id="estado" class="state">Listo para enviar pruebas.</div>
    </div>
  </div>

  <script>
    (function () {
      "use strict";

      var apiUrlConfig = <?php echo json_encode($apiUrlConfig, JSON_UNESCAPED_SLASHES); ?>;
      var apiUrlInput = document.getElementById("apiUrl");
      var emailVendedorInput = document.getElementById("emailVendedor");
      var emailClienteInput = document.getElementById("emailCliente");
      var payloadJson = document.getElementById("payloadJson");
      var estado = document.getElementById("estado");
      var adjuntosExtra = document.getElementById("adjuntosExtra");

      function getDefaultApiUrl() {
        if (apiUrlConfig && String(apiUrlConfig).trim() !== "") {
          return String(apiUrlConfig).trim();
        }
        var p = window.location.pathname || "/";
        var base = p.replace(/\/financiamiento\/[^/]*$/, "");
        return window.location.origin + (base === "/" ? "" : base) + "/api/solicitud_publica.php";
      }

      function encodeBase64Url(text) {
        var b64 = btoa(unescape(encodeURIComponent(text)));
        return b64.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
      }

      function setState(msg, ok) {
        estado.textContent = msg;
        estado.classList.remove("ok", "err");
        estado.classList.add(ok ? "ok" : "err");
      }

      function todayMinusYears(years) {
        var d = new Date();
        d.setFullYear(d.getFullYear() - years);
        var mm = String(d.getMonth() + 1).padStart(2, "0");
        var dd = String(d.getDate()).padStart(2, "0");
        var yyyy = String(d.getFullYear());
        return mm + "/" + dd + "/" + yyyy;
      }

      function buildTestPayload() {
        var ts = Date.now();
        var emailCliente = (emailClienteInput.value || "").trim() || ("qa.cliente+" + ts + "@example.com");
        return {
          cliente_nombre: "QA Cliente " + ts,
          cliente_estado_civil: "Soltero/a",
          cliente_sexo: "M",
          cliente_id: "8-TEST-" + String(ts).slice(-6),
          cliente_nacimiento: todayMinusYears(31),
          cliente_edad: 31,
          cliente_nacionalidad: "Panamena",
          cliente_dependientes: 1,
          cliente_correo: emailCliente,
          cliente_peso: 175,
          cliente_estatura: 1.75,
          vivienda: "Alquilada",
          vivienda_monto: 450.00,
          provincia: "Panama",
          distrito: "Panama",
          corregimiento: "Bella Vista",
          prov_dist_corr: "Panama, Panama, Bella Vista",
          tel_residencia: "2233-4455",
          barriada: "El Cangrejo",
          barriada_calle_casa: "El Cangrejo - Calle 50",
          celular_cliente: "6123-4567",
          casa_edif: "Apartamento",
          numero_casa_apto: "Apto 10B",
          edificio_apto: "Apartamento, Apto 10B",
          direccion: "Calle 50, edificio de prueba",
          correo_residencial: emailCliente,
          empresa_nombre: "Empresa QA SA",
          empresa_ocupacion: "Analista QA",
          empresa_anios: "4",
          empresa_telefono: "3000-1111",
          empresa_salario: 1450.75,
          empresa_direccion: "Costa del Este, torre test",
          otros_ingresos: "Freelance 200 mensual",
          ocupacion_otros: "Consultoria",
          trabajo_anterior: "Empresa anterior de QA",
          tiene_conyuge: 0,
          con_nombre: "",
          con_estado_civil: "",
          con_sexo: "",
          con_id: "",
          con_nacimiento: "",
          con_edad: "",
          con_nacionalidad: "",
          con_dependientes: "",
          con_correo: "",
          con_empresa: "",
          con_ocupacion: "",
          con_anios: "",
          con_tel: "",
          con_salario: "",
          con_direccion: "",
          con_otros_ingresos: "",
          con_trabajo_anterior: "",
          refp1_nombre: "Referencia Personal Uno",
          refp1_cel: "6333-1000",
          refp1_dir_res: "Via Espana",
          refp1_dir_lab: "Zona Bancaria",
          refp2_nombre: "Referencia Personal Dos",
          refp2_cel: "6333-2000",
          refp2_dir_res: "San Francisco",
          refp2_dir_lab: "Obarrio",
          reff1_nombre: "Referencia Familiar Uno",
          reff1_cel: "6444-1000",
          reff1_dir_res: "Parque Lefevre",
          reff1_dir_lab: "Chanis",
          reff2_nombre: "Referencia Familiar Dos",
          reff2_cel: "6444-2000",
          reff2_dir_res: "Juan Diaz",
          reff2_dir_lab: "Tocumen",
          marca_auto: "Toyota",
          modelo_auto: "Corolla",
          anio_auto: 2021,
          kms_cod_auto: 28000,
          precio_venta: 16500.00,
          abono: 2500.00,
          sucursal: "Sucursal Test",
          nombre_gestor: "Gestor QA",
          comentarios_gestor: "Solicitud de prueba automatizada para validar correo y adjuntos.",
          firma: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAusB9WnR0mQAAAAASUVORK5CYII="
        };
      }

      function refreshPayload() {
        payloadJson.value = JSON.stringify(buildTestPayload(), null, 2);
      }

      function parsePayload() {
        try {
          var obj = JSON.parse(payloadJson.value || "{}");
          return { ok: true, value: obj };
        } catch (err) {
          return { ok: false, error: err };
        }
      }

      function validateMinimum(payload) {
        if (!payload.cliente_nombre || !String(payload.cliente_nombre).trim()) {
          throw new Error("cliente_nombre es obligatorio.");
        }
        if (!payload.cliente_id || !String(payload.cliente_id).trim()) {
          throw new Error("cliente_id es obligatorio.");
        }
      }

      async function enviar() {
        var parsed = parsePayload();
        if (!parsed.ok) {
          setState("JSON invalido: " + parsed.error.message, false);
          return;
        }

        try {
          validateMinimum(parsed.value);
        } catch (err) {
          setState("Validacion minima fallo: " + err.message, false);
          return;
        }

        var endpoint = (apiUrlInput.value || "").trim();
        if (!endpoint) {
          setState("Debes indicar un endpoint API.", false);
          return;
        }

        var payload = parsed.value;
        var emailVendedor = (emailVendedorInput.value || "").trim();
        if (emailVendedor) {
          payload.token = encodeBase64Url(emailVendedor);
        } else {
          delete payload.token;
        }

        var fd = new FormData();
        fd.append("payload", JSON.stringify(payload));

        if (adjuntosExtra.files && adjuntosExtra.files.length > 0) {
          for (var i = 0; i < adjuntosExtra.files.length; i++) {
            fd.append("adjuntos_extra[]", adjuntosExtra.files[i]);
          }
        }

        setState("Enviando solicitud de prueba...\nEndpoint: " + endpoint, true);
        try {
          var res = await fetch(endpoint, {
            method: "POST",
            body: fd
          });

          var raw = await res.text();
          var data;
          try {
            data = JSON.parse(raw);
          } catch (e) {
            data = { raw: raw };
          }

          if (!res.ok || (data && data.success === false)) {
            setState(
              "Fallo en envio (" + res.status + ").\n" +
              JSON.stringify(data, null, 2),
              false
            );
            return;
          }

          setState(
            "Envio exitoso.\n" +
            "HTTP: " + res.status + "\n" +
            "Respuesta: " + JSON.stringify(data, null, 2) + "\n\n" +
            "Siguiente paso: revisar correo del vendedor/cliente para validar que llegaron PDF y adjuntos.",
            true
          );
        } catch (err) {
          setState("Error de red: " + err.message, false);
        }
      }

      apiUrlInput.value = getDefaultApiUrl();
      refreshPayload();

      document.getElementById("btnAutoFill").addEventListener("click", refreshPayload);
      document.getElementById("btnValidar").addEventListener("click", function () {
        var parsed = parsePayload();
        if (!parsed.ok) {
          setState("JSON invalido: " + parsed.error.message, false);
          return;
        }
        try {
          validateMinimum(parsed.value);
          setState("JSON valido y listo para enviar.", true);
        } catch (err) {
          setState("Validacion minima fallo: " + err.message, false);
        }
      });
      document.getElementById("btnEnviar").addEventListener("click", enviar);
    })();
  </script>
</body>
</html>
