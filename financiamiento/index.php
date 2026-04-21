<?php
/**
 * Formulario público de Solicitud de Financiamiento (Wizard).
 * Acceso sin login. Si se accede con ?e=EMAIL_CODIFICADO, al enviar se envía por correo el PDF a ese email.
 */
require_once __DIR__ . '/config.php';
$tokenLink = isset($_GET['e']) ? trim($_GET['e']) : '';
$apiUrlConfig = defined('FINANCIAMIENTO_API_URL') && FINANCIAMIENTO_API_URL !== '' ? FINANCIAMIENTO_API_URL : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Solicitud de Financiamiento - Solicitud de Crédito</title>
  <script>
    (function(){
      try{
        var p = JSON.parse(localStorage.getItem("financiamiento_visual_prefs") || "{}");
        var h = document.documentElement;
        if (p.theme === "light") h.classList.add("theme-light");
        var fs = p.fs === "lg" ? "1.12" : p.fs === "xl" ? "1.24" : "1";
        h.style.setProperty("--fs-scale", fs);
        if (p.bold) h.classList.add("a11y-bold");
      }catch(e){}
    })();
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

  <style>
    :root{
      --bg:#0b1220;
      --card:#0f1b33;
      --muted:#9fb0d0;
      --text:#eaf0ff;
      --line:rgba(255,255,255,.12);
      --accent:#4ea1ff;
      --danger:#ff5d5d;
      --ok:#34d399;
      --shadow: 0 10px 25px rgba(0,0,0,.25);
      --radius: 16px;
      --fs-scale: 1;
      --ink-signature: #eaf0ff;
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-size: calc(15px * var(--fs-scale));
      background: radial-gradient(1200px 600px at 30% 0%, rgba(78,161,255,.15), transparent 55%),
                  radial-gradient(900px 500px at 80% 10%, rgba(52,211,153,.12), transparent 55%),
                  var(--bg);
      color: var(--text);
      min-height:100vh;
      padding: 18px;
    }

    .wrap{
      max-width: 980px;
      margin: 0 auto;
      display:grid;
      gap: 14px;
    }

    header{
      display:flex;
      flex-direction:column;
      gap: 8px;
      padding: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    header .top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    h1{
      margin:0;
      font-size: calc(18px * var(--fs-scale));
      letter-spacing:.2px;
    }

    .subtitle{
      margin:0;
      color: var(--muted);
      font-size: calc(13px * var(--fs-scale));
      line-height: 1.35;
    }

    .actions{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    button, .btn{
      border: 1px solid var(--line);
      background: rgba(255,255,255,.06);
      color: var(--text);
      padding: 10px 12px;
      border-radius: 12px;
      cursor:pointer;
      font-weight: 600;
      font-size: calc(14px * var(--fs-scale));
      transition: transform .06s ease, background .15s ease, border-color .15s ease;
      user-select:none;
    }
    button:hover{ background: rgba(255,255,255,.10); }
    button:active{ transform: translateY(1px); }
    button.primary{
      background: rgba(78,161,255,.18);
      border-color: rgba(78,161,255,.35);
    }
    button.primary:hover{ background: rgba(78,161,255,.25); }
    button.danger{
      background: rgba(255,93,93,.12);
      border-color: rgba(255,93,93,.3);
    }
    button:disabled{ opacity: .6; cursor: not-allowed; }

    .progressCard{
      padding: 14px 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      display:grid;
      gap: 10px;
    }

    .progressRow{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .steps{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .chip{
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--line);
      color: var(--muted);
      font-size: calc(12px * var(--fs-scale));
      background: rgba(255,255,255,.03);
      display:flex;
      gap: 6px;
      align-items:center;
      max-width: 100%;
      white-space: nowrap;
    }
    .chip strong{ color: var(--text); font-weight:700; }
    .chip.active{
      border-color: rgba(78,161,255,.5);
      background: rgba(78,161,255,.12);
      color: var(--text);
    }
    .chip.done{
      border-color: rgba(52,211,153,.45);
      background: rgba(52,211,153,.10);
      color: var(--text);
    }

    .bar{
      height: 10px;
      border-radius: 999px;
      background: rgba(255,255,255,.08);
      border: 1px solid var(--line);
      overflow:hidden;
    }
    .bar > div{
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, rgba(78,161,255,.9), rgba(52,211,153,.9));
      border-radius: 999px;
      transition: width .25s ease;
    }

    form{
      padding: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    fieldset{
      border: 0;
      padding: 0;
      margin: 0;
      display:none;
      animation: fadeIn .16s ease;
    }
    fieldset.active{ display:block; }

    @keyframes fadeIn{
      from{ opacity: .6; transform: translateY(6px); }
      to{ opacity: 1; transform: translateY(0); }
    }

    .sectionTitle{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 12px;
      margin-bottom: 10px;
      flex-wrap: wrap;
    }
    .sectionTitle h2{
      margin:0;
      font-size: calc(16px * var(--fs-scale));
    }
    .sectionTitle p{
      margin: 4px 0 0 0;
      font-size: calc(13px * var(--fs-scale));
      color: var(--muted);
      line-height: 1.35;
      max-width: 70ch;
    }

    .grid{
      display:grid;
      gap: 12px;
      grid-template-columns: repeat(12, 1fr);
    }

    .col-12{ grid-column: span 12; }
    .col-6{ grid-column: span 12; }
    .col-4{ grid-column: span 12; }
    .col-3{ grid-column: span 12; }

    @media (min-width: 720px){
      .col-6{ grid-column: span 6; }
      .col-4{ grid-column: span 4; }
      .col-3{ grid-column: span 3; }
    }

    label{
      display:block;
      font-size: calc(12px * var(--fs-scale));
      color: var(--muted);
      margin-bottom: 6px;
    }

    input, select, textarea{
      width: 100%;
      padding: 12px 12px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(15,27,51,.6);
      color: var(--text);
      font-size: calc(15px * var(--fs-scale));
      outline: none;
    }
    input:focus, select:focus, textarea:focus{
      border-color: rgba(78,161,255,.55);
      box-shadow: 0 0 0 3px rgba(78,161,255,.15);
    }
    input.input-format-ok{
      border-color: rgba(52,211,153,.65) !important;
      box-shadow: 0 0 0 3px rgba(52,211,153,.14);
    }
    input.input-format-bad{
      border-color: rgba(255,93,93,.8) !important;
      box-shadow: 0 0 0 3px rgba(255,93,93,.14);
    }
    html.theme-light input.input-format-ok{
      border-color: rgba(5,150,105,.55) !important;
      box-shadow: 0 0 0 3px rgba(5,150,105,.12);
    }
    html.theme-light input.input-format-bad{
      border-color: rgba(185,28,28,.65) !important;
      box-shadow: 0 0 0 3px rgba(185,28,28,.12);
    }

    textarea{ min-height: 92px; resize: vertical; }

    .nacimiento-hint{
      margin-top: 4px;
      font-size: calc(11px * var(--fs-scale));
      color: var(--muted);
      line-height: 1.35;
    }
    .nacimiento-hint.err{
      color: var(--danger);
      font-weight: 700;
    }
    .nacimiento-hint.ok{
      color: var(--ok);
      font-weight: 700;
    }

    .hint{
      margin-top: 6px;
      font-size: calc(12px * var(--fs-scale));
      color: var(--muted);
    }

    .error{
      margin-top: 6px;
      font-size: calc(12px * var(--fs-scale));
      color: var(--danger);
      display:none;
    }

    .rowActions{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
      margin-top: 16px;
      flex-wrap: wrap;
    }

    .navBtns{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .toast{
      position: fixed;
      left: 50%;
      bottom: 16px;
      transform: translateX(-50%);
      background: rgba(15,27,51,.92);
      border: 1px solid var(--line);
      color: var(--text);
      padding: 10px 12px;
      border-radius: 999px;
      box-shadow: var(--shadow);
      font-size: calc(13px * var(--fs-scale));
      display:none;
      max-width: calc(100% - 24px);
    }
    .toast.show{ display:block; }
    .toast.ok{ border-color: rgba(52,211,153,.5); background: rgba(52,211,153,.15); }
    .toast.err{ border-color: rgba(255,93,93,.5); background: rgba(255,93,93,.15); }

    /* Tema claro (alto contraste lectura) */
    html.theme-light{
      --bg:#f1f5f9;
      --card:#ffffff;
      --muted:#334155;
      --text:#0f172a;
      --line:rgba(15,23,42,.14);
      --accent:#1d4ed8;
      --danger:#b91c1c;
      --ok:#047857;
      --shadow: 0 10px 28px rgba(15,23,42,.1);
      --ink-signature:#0f172a;
    }
    html.theme-light body{
      background: radial-gradient(1000px 520px at 28% 0%, rgba(29,78,216,.1), transparent 55%),
                  radial-gradient(780px 420px at 82% 8%, rgba(4,120,87,.08), transparent 55%),
                  var(--bg);
    }
    html.theme-light header,
    html.theme-light .progressCard,
    html.theme-light form{
      background: rgba(255,255,255,.92);
    }
    html.theme-light input,
    html.theme-light select,
    html.theme-light textarea{
      background: #fff;
      border-color: rgba(15,23,42,.2);
      color: var(--text);
    }
    html.theme-light input:focus,
    html.theme-light select:focus,
    html.theme-light textarea:focus{
      border-color: rgba(29,78,216,.55);
      box-shadow: 0 0 0 3px rgba(29,78,216,.12);
    }
    html.theme-light .toast{
      background: rgba(255,255,255,.96);
      color: var(--text);
    }
    html.theme-light .toast.ok{
      background: rgba(4,120,87,.12);
    }
    html.theme-light .toast.err{
      background: rgba(185,28,28,.12);
    }
    html.theme-light .signature-wrap{
      border-color: rgba(15,23,42,.22) !important;
      background: #fff !important;
    }
    html.theme-light .consent-text{
      background: rgba(15,23,42,.04) !important;
      color: var(--text) !important;
    }

    html.a11y-bold body,
    html.a11y-bold h1,
    html.a11y-bold .subtitle,
    html.a11y-bold label,
    html.a11y-bold .hint,
    html.a11y-bold .error,
    html.a11y-bold .chip,
    html.a11y-bold button,
    html.a11y-bold input,
    html.a11y-bold select,
    html.a11y-bold textarea,
    html.a11y-bold .sectionTitle h2,
    html.a11y-bold .sectionTitle p,
    html.a11y-bold .toast{
      font-weight: 700;
    }

    /* Botón solapa: configuración visual */
    .visual-dock{
      position: fixed;
      top: 10px;
      right: 0;
      z-index: 10000;
      max-width: min(100vw - 8px, 360px);
      width: fit-content;
      pointer-events: none;
    }
    .visual-dock-slide{
      display: flex;
      flex-direction: row;
      align-items: flex-start;
      justify-content: flex-end;
      filter: drop-shadow(-6px 8px 22px rgba(0,0,0,.22));
      pointer-events: none;
    }
    .visual-panel{
      width: min(320px, calc(100vw - 52px));
      min-width: 0;
      padding: 14px 16px;
      border-radius: 14px 0 0 14px;
      border: 1px solid var(--line);
      border-right: 0;
      background: var(--card);
      color: var(--text);
      transform: translateX(100%);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: transform .25s ease, opacity .2s ease, visibility .2s ease;
    }
    .visual-panel-title{
      margin: 0 0 6px 0;
      font-size: calc(15px * var(--fs-scale));
      font-weight: 800;
    }
    .visual-panel-hint{
      margin: 0 0 12px 0;
      font-size: calc(12px * var(--fs-scale));
      color: var(--muted);
      line-height: 1.4;
    }
    .visual-field{
      margin-bottom: 12px;
    }
    .visual-field label.visual-label-inline{
      display: block;
      font-size: calc(12px * var(--fs-scale));
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--text);
    }
    .visual-options{
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }
    .visual-options label.pick{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: calc(13px * var(--fs-scale));
      cursor: pointer;
      color: var(--text);
    }
    .visual-options select{
      max-width: 100%;
      flex: 1;
      min-width: 140px;
    }
    .visual-tab{
      flex: 0 0 34px;
      width: 34px;
      margin: 0;
      padding: 8px 4px;
      border: 1px solid var(--line);
      border-right: 0;
      border-radius: 12px 0 0 12px;
      background: linear-gradient(165deg, rgba(78,161,255,.35), rgba(15,27,51,.92));
      color: var(--text);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: inherit;
      pointer-events: auto;
      transition: width .2s ease;
    }
    html.theme-light .visual-tab{
      background: linear-gradient(165deg, rgba(59,130,246,.35), #f8fafc);
      color: var(--text);
    }
    .visual-gear{
      font-size: calc(16px * var(--fs-scale));
      line-height: 1;
      display: inline-block;
      animation: spinGear 2.2s linear infinite;
      transform-origin: 50% 50%;
    }
    @keyframes spinGear{
      from{ transform: rotate(0deg); }
      to{ transform: rotate(360deg); }
    }
    .visual-tab-label{
      display: none;
      font-size: calc(11px * var(--fs-scale));
      font-weight: 800;
      line-height: 1.2;
      text-align: center;
      margin-left: 8px;
    }
    .visual-dock.is-open{
      pointer-events: auto;
    }
    .visual-dock.is-open .visual-dock-slide{
      pointer-events: auto;
    }
    .visual-dock.is-open .visual-panel{
      transform: translateX(0);
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .visual-dock.is-open .visual-tab{
      width: auto;
      min-width: 34px;
      max-width: 160px;
      padding: 8px 10px;
    }
    .visual-dock.is-open .visual-tab-label{
      display: inline-block;
    }

    /* Solapa de identificación (captura cédula/firma) */
    .id-dock{
      position: fixed;
      top: 56px;
      right: 0;
      z-index: 9999;
      max-width: min(100vw - 8px, 380px);
      width: fit-content;
      pointer-events: none;
    }
    .id-dock-slide{
      display: flex;
      flex-direction: row;
      align-items: flex-start;
      justify-content: flex-end;
      filter: drop-shadow(-6px 8px 22px rgba(0,0,0,.22));
      pointer-events: none;
    }
    .id-panel{
      width: min(340px, calc(100vw - 52px));
      min-width: 0;
      padding: 14px 16px;
      border-radius: 14px 0 0 14px;
      border: 1px solid var(--line);
      border-right: 0;
      background: var(--card);
      color: var(--text);
      transform: translateX(100%);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: transform .25s ease, opacity .2s ease, visibility .2s ease;
    }
    .id-dock.is-open{ pointer-events: auto; }
    .id-dock.is-open .id-dock-slide{ pointer-events: auto; }
    .id-dock.is-open .id-panel{
      transform: translateX(0);
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .id-tab{
      flex: 0 0 34px;
      width: 34px;
      margin: 0;
      padding: 8px 4px;
      border: 1px solid var(--line);
      border-right: 0;
      border-radius: 12px 0 0 12px;
      background: linear-gradient(165deg, rgba(16,185,129,.35), rgba(15,27,51,.92));
      color: var(--text);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: inherit;
      pointer-events: auto;
      transition: width .2s ease;
    }
    .id-dock.is-open .id-tab{
      width: auto;
      min-width: 34px;
      max-width: 160px;
      padding: 8px 10px;
    }
    .id-tab-label{
      display: none;
      font-size: calc(11px * var(--fs-scale));
      font-weight: 800;
      line-height: 1.2;
      text-align: center;
      margin-left: 8px;
    }
    .id-dock.is-open .id-tab-label{ display: inline-block; }
    .id-tab-icon{
      font-size: calc(15px * var(--fs-scale));
      line-height: 1;
    }
    .id-action-row{
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }
    .id-mini-preview{
      margin-top: 10px;
      border: 1px dashed var(--line);
      border-radius: 10px;
      min-height: 90px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: rgba(255,255,255,.03);
    }
    .id-mini-preview img{
      width: 100%;
      height: auto;
      display: block;
    }
    .adjuntos-file-input{
      padding: 10px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: rgba(15,27,51,.5);
      color: var(--text);
      width: 100%;
      font-size: calc(13px * var(--fs-scale));
    }
    html.theme-light .adjuntos-file-input{
      background: #fff;
      color: var(--text);
    }
    .adjuntos-extra-list{
      list-style: none;
      margin: 10px 0 0;
      padding: 0;
      display: grid;
      gap: 8px;
    }
    .adjuntos-extra-item{
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      font-size: calc(13px * var(--fs-scale));
    }
    .signature-trace-bg{
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      max-width: 90%;
      max-height: 90%;
      opacity: .32;
      pointer-events: none;
      z-index: 0;
    }
    #firmaCanvas{ z-index: 1; }

    /* Modal recorte: pie con fondo claro — forzar tinta oscura (el formulario fuerza color claro en .btn global) */
    #idCropperModal .id-crop-footer{
      color: #0f172a;
    }
    #idCropperModal .id-crop-footer #idCropperHelp{
      color: #334155 !important;
    }
    #idCropperModal .id-crop-footer #idCropCancelBtn{
      background: #fff;
      border: 1px solid #64748b;
      color: #0f172a;
    }
    #idCropperModal .id-crop-footer #idCropCancelBtn:hover{
      background: #f1f5f9;
      border-color: #475569;
    }
    #idCropperModal .id-crop-footer #idCropApplyBtn{
      background: #2563eb;
      border: 1px solid #1d4ed8;
      color: #fff;
    }
    #idCropperModal .id-crop-footer #idCropApplyBtn:hover{
      background: #1d4ed8;
      border-color: #1e40af;
    }
    #idCropperModal .id-crop-footer #idRotateLeftBtn,
    #idCropperModal .id-crop-footer #idRotateRightBtn{
      background: #e2e8f0;
      border: 1px solid #94a3b8;
      color: #0f172a;
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2px;
      width: 56px;
      height: 56px;
      padding: 6px 4px;
      border-radius: 50%;
      line-height: 1;
    }
    #idCropperModal .id-crop-footer #idRotateLeftBtn svg,
    #idCropperModal .id-crop-footer #idRotateRightBtn svg{
      display: block;
      flex-shrink: 0;
    }
    #idCropperModal .id-crop-footer .id-rotate-cap{
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.02em;
      color: #0f172a;
    }
  </style>
</head>

<body>
  <div class="wrap">
    <header>
      <div class="top">
        <div>
          <h1>Solicitud de Financiamiento</h1>
          <p class="subtitle">
            Complete los pasos. El progreso se guarda en este dispositivo. Al enviar, su solicitud será registrada y nos pondremos en contacto.
          </p>
        </div>
        <div class="actions">
          <button type="button" id="btnSaveExit">Guardar y salir</button>
          <button type="button" class="danger" id="btnClear">Borrar progreso</button>
        </div>
      </div>
    </header>

    <div class="progressCard">
      <div class="progressRow">
        <div class="steps" id="stepChips"></div>
        <div class="chip" id="saveState"><strong>Estado:</strong> <span>Sin guardar</span></div>
      </div>
      <div class="bar" aria-label="progreso">
        <div id="progressBar"></div>
      </div>
    </div>

    <form id="wizardForm" novalidate>
      <fieldset data-step="0" class="active">
        <div class="sectionTitle">
          <div>
            <h2>A. Información del Cliente</h2>
            <p>Datos personales del solicitante.</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-6">
            <label for="cliente_nombre">Nombre y apellido *</label>
            <input id="cliente_nombre" name="cliente_nombre" required maxlength="90" autocomplete="name"/>
            <div class="error" data-error-for="cliente_nombre"></div>
          </div>
          <div class="col-3">
            <label for="cliente_estado_civil">Estado civil *</label>
            <select id="cliente_estado_civil" name="cliente_estado_civil" required>
              <option value="">Seleccione…</option>
              <option>Soltero/a</option>
              <option>Casado/a</option>
              <option>Unión libre</option>
              <option>Divorciado/a</option>
              <option>Viudo/a</option>
            </select>
            <div class="error" data-error-for="cliente_estado_civil"></div>
          </div>
          <div class="col-3">
            <label for="cliente_sexo">Sexo *</label>
            <select id="cliente_sexo" name="cliente_sexo" required>
              <option value="">Seleccione…</option>
              <option value="F">F</option>
              <option value="M">M</option>
              <option value="Otro">Otro</option>
            </select>
            <div class="error" data-error-for="cliente_sexo"></div>
          </div>
          <div class="col-6">
            <label for="cliente_id">Cédula / Pasaporte / RUC *</label>
            <input id="cliente_id" name="cliente_id" required maxlength="30" />
            <div class="error" data-error-for="cliente_id"></div>
          </div>
          <div class="col-3">
            <label for="cliente_nacimiento">Fecha de nacimiento *</label>
            <input id="cliente_nacimiento" name="cliente_nacimiento" type="text" inputmode="numeric" autocomplete="bday" maxlength="10" placeholder="MM/DD/YYYY" required />
            <p id="clienteNacimientoHint" class="nacimiento-hint">Digite solo números; formato MM/DD/YYYY (mes/día/año). La edad debe estar entre 18 y 100 años.</p>
            <div class="error" data-error-for="cliente_nacimiento"></div>
          </div>
          <div class="col-3">
            <label for="cliente_edad">Edad *</label>
            <input id="cliente_edad" name="cliente_edad" inputmode="numeric" required readonly tabindex="-1" title="Se calcula automáticamente con la fecha de nacimiento" />
            <div class="error" data-error-for="cliente_edad"></div>
          </div>
          <div class="col-4">
            <label for="cliente_nacionalidad">Nacionalidad *</label>
            <input id="cliente_nacionalidad" name="cliente_nacionalidad" required maxlength="40" />
            <div class="error" data-error-for="cliente_nacionalidad"></div>
          </div>
          <div class="col-4">
            <label for="cliente_dependientes">Dependientes</label>
            <input id="cliente_dependientes" name="cliente_dependientes" inputmode="numeric" pattern="^\d{0,2}$" placeholder="0" />
            <div class="error" data-error-for="cliente_dependientes"></div>
          </div>
          <div class="col-4">
            <label for="cliente_correo">Correo *</label>
            <input id="cliente_correo" name="cliente_correo" type="email" required autocomplete="email" />
            <div class="error" data-error-for="cliente_correo"></div>
          </div>
          <div class="col-3">
            <label for="cliente_peso">Peso (lbs)</label>
            <input id="cliente_peso" name="cliente_peso" inputmode="decimal" placeholder="lbs" />
            <div class="error" data-error-for="cliente_peso"></div>
          </div>
          <div class="col-3">
            <label for="cliente_estatura">Estatura (m)</label>
            <input id="cliente_estatura" name="cliente_estatura" inputmode="decimal" placeholder="Ej: 1.75" step="0.01" />
            <div class="error" data-error-for="cliente_estatura"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 1 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="1">
        <div class="sectionTitle">
          <div>
            <h2>B. Dirección Residencial</h2>
            <p>Dirección, teléfonos y condición de vivienda.</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-12">
            <label>Condición de vivienda *</label>
            <div class="grid" style="gap:10px">
              <div class="col-3">
                <label class="chip" style="display:flex;gap:8px;align-items:center;justify-content:flex-start">
                  <input type="radio" name="vivienda" value="Propia" required />
                  Propia
                </label>
              </div>
              <div class="col-3">
                <label class="chip" style="display:flex;gap:8px;align-items:center;justify-content:flex-start">
                  <input type="radio" name="vivienda" value="Hipotecada" required />
                  Hipotecada
                </label>
              </div>
              <div class="col-3">
                <label class="chip" style="display:flex;gap:8px;align-items:center;justify-content:flex-start">
                  <input type="radio" name="vivienda" value="Alquilada" required />
                  Alquilada
                </label>
              </div>
              <div class="col-3">
                <label for="vivienda_monto">Monto (USD)</label>
                <input id="vivienda_monto" name="vivienda_monto" inputmode="decimal" placeholder="0.00" />
                <div class="error" data-error-for="vivienda_monto"></div>
              </div>
            </div>
            <div class="error" data-error-for="vivienda"></div>
          </div>
          <div class="col-3">
            <label for="provincia">Provincia *</label>
            <input id="provincia" name="provincia" required maxlength="60" />
            <div class="error" data-error-for="provincia"></div>
          </div>
          <div class="col-3">
            <label for="distrito">Distrito *</label>
            <input id="distrito" name="distrito" required maxlength="60" />
            <div class="error" data-error-for="distrito"></div>
          </div>
          <div class="col-3">
            <label for="corregimiento">Corregimiento *</label>
            <input id="corregimiento" name="corregimiento" required maxlength="60" />
            <div class="error" data-error-for="corregimiento"></div>
          </div>
          <div class="col-6">
            <label for="tel_residencia">Teléfono de residencia</label>
            <input id="tel_residencia" name="tel_residencia" inputmode="tel" placeholder="Ej: 2XXX-XXXX" />
            <div class="error" data-error-for="tel_residencia"></div>
          </div>
          <div class="col-6">
            <label for="barriada">Barriada *</label>
            <input id="barriada" name="barriada" required maxlength="120" />
            <div class="error" data-error-for="barriada"></div>
          </div>
          <div class="col-6">
            <label for="celular_cliente">Celular *</label>
            <input id="celular_cliente" name="celular_cliente" required inputmode="tel" placeholder="Ej: 6XXX-XXXX" />
            <div class="error" data-error-for="celular_cliente"></div>
          </div>
          <div class="col-6">
            <label for="casa_edif">Casa / Edif</label>
            <select id="casa_edif" name="casa_edif">
              <option value="">Seleccione…</option>
              <option value="Casa">Casa</option>
              <option value="Edificio">Edificio</option>
            </select>
            <div class="error" data-error-for="casa_edif"></div>
          </div>
          <div class="col-6">
            <label for="numero_casa_apto"># de casa / Apto</label>
            <input id="numero_casa_apto" name="numero_casa_apto" maxlength="120" />
            <div class="error" data-error-for="numero_casa_apto"></div>
          </div>
          <div class="col-12">
            <label for="direccion">Dirección completa *</label>
            <textarea id="direccion" name="direccion" required maxlength="300"></textarea>
            <div class="error" data-error-for="direccion"></div>
          </div>
          <div class="col-6">
            <label for="correo_residencial">Correo electrónico</label>
            <input id="correo_residencial" name="correo_residencial" type="email" placeholder="Opcional" />
            <div class="error" data-error-for="correo_residencial"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 2 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="2">
        <div class="sectionTitle">
          <div>
            <h2>C. Información Laboral</h2>
            <p>Empleo actual, salario e ingresos adicionales.</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-6">
            <label for="empresa_nombre">Nombre de la empresa *</label>
            <input id="empresa_nombre" name="empresa_nombre" required maxlength="120" />
            <div class="error" data-error-for="empresa_nombre"></div>
          </div>
          <div class="col-6">
            <label for="empresa_ocupacion">Ocupación *</label>
            <input id="empresa_ocupacion" name="empresa_ocupacion" required maxlength="80" />
            <div class="error" data-error-for="empresa_ocupacion"></div>
          </div>
          <div class="col-4">
            <label for="empresa_anios">Años de servicio *</label>
            <input id="empresa_anios" name="empresa_anios" inputmode="numeric" required pattern="^\d{1,2}$" placeholder="Ej: 3" />
            <div class="error" data-error-for="empresa_anios"></div>
          </div>
          <div class="col-4">
            <label for="empresa_telefono">Teléfono</label>
            <input id="empresa_telefono" name="empresa_telefono" inputmode="tel" />
            <div class="error" data-error-for="empresa_telefono"></div>
          </div>
          <div class="col-4">
            <label for="empresa_salario">Salario (USD) *</label>
            <input id="empresa_salario" name="empresa_salario" inputmode="decimal" required placeholder="0.00" />
            <div class="error" data-error-for="empresa_salario"></div>
          </div>
          <div class="col-12">
            <label for="empresa_direccion">Dirección *</label>
            <textarea id="empresa_direccion" name="empresa_direccion" required maxlength="300"></textarea>
            <div class="error" data-error-for="empresa_direccion"></div>
          </div>
          <div class="col-6">
            <label for="otros_ingresos">Independiente y/o otros ingresos (detalle)</label>
            <input id="otros_ingresos" name="otros_ingresos" maxlength="140" placeholder="Opcional" />
            <div class="error" data-error-for="otros_ingresos"></div>
          </div>
          <div class="col-6">
            <label for="ocupacion_otros">Ocupación (otros ingresos)</label>
            <input id="ocupacion_otros" name="ocupacion_otros" maxlength="80" placeholder="Opcional" />
            <div class="error" data-error-for="ocupacion_otros"></div>
          </div>
          <div class="col-12">
            <label for="trabajo_anterior">Trabajo anterior (si tiene menos de 2 años)</label>
            <input id="trabajo_anterior" name="trabajo_anterior" maxlength="140" placeholder="Opcional" />
            <div class="error" data-error-for="trabajo_anterior"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 3 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="3">
        <div class="sectionTitle">
          <div>
            <h2>D. Solicitante adicional y/o Cónyuge</h2>
            <p>Completar si aplica.</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-12">
            <label class="chip" style="display:flex;gap:10px;align-items:center;justify-content:flex-start">
              <input type="checkbox" id="tiene_conyuge" name="tiene_conyuge" />
              Incluir datos de cónyuge / solicitante adicional
            </label>
            <div class="hint">Si no aplica, deja desmarcado y podrás continuar igual.</div>
          </div>
          <div class="col-6">
            <label for="con_nombre">Nombre y apellido</label>
            <input id="con_nombre" name="con_nombre" maxlength="90" />
            <div class="error" data-error-for="con_nombre"></div>
          </div>
          <div class="col-3">
            <label for="con_estado_civil">Estado civil</label>
            <select id="con_estado_civil" name="con_estado_civil">
              <option value="">Seleccione…</option>
              <option>Soltero/a</option>
              <option>Casado/a</option>
              <option>Unión libre</option>
              <option>Divorciado/a</option>
              <option>Viudo/a</option>
            </select>
            <div class="error" data-error-for="con_estado_civil"></div>
          </div>
          <div class="col-3">
            <label for="con_sexo">Sexo</label>
            <select id="con_sexo" name="con_sexo">
              <option value="">Seleccione…</option>
              <option value="F">F</option>
              <option value="M">M</option>
              <option value="Otro">Otro</option>
            </select>
            <div class="error" data-error-for="con_sexo"></div>
          </div>
          <div class="col-6">
            <label for="con_id">Cédula / Pasaporte / RUC</label>
            <input id="con_id" name="con_id" maxlength="30" />
            <div class="error" data-error-for="con_id"></div>
          </div>
          <div class="col-3">
            <label for="con_nacimiento">Fecha de nacimiento</label>
            <input id="con_nacimiento" name="con_nacimiento" type="text" inputmode="numeric" autocomplete="off" maxlength="10" placeholder="MM/DD/YYYY (opcional)" />
            <p id="conNacimientoHint" class="nacimiento-hint">Opcional. Formato MM/DD/YYYY (mes/día/año). La edad se calcula sola; sin límite de edad.</p>
            <div class="error" data-error-for="con_nacimiento"></div>
          </div>
          <div class="col-3">
            <label for="con_edad">Edad</label>
            <input id="con_edad" name="con_edad" inputmode="numeric" readonly tabindex="-1" title="Se calcula con la fecha de nacimiento (opcional)" />
            <div class="error" data-error-for="con_edad"></div>
          </div>
          <div class="col-4">
            <label for="con_nacionalidad">Nacionalidad</label>
            <input id="con_nacionalidad" name="con_nacionalidad" maxlength="40" />
            <div class="error" data-error-for="con_nacionalidad"></div>
          </div>
          <div class="col-4">
            <label for="con_dependientes">Dependientes</label>
            <input id="con_dependientes" name="con_dependientes" inputmode="numeric" pattern="^\d{0,2}$" />
            <div class="error" data-error-for="con_dependientes"></div>
          </div>
          <div class="col-4">
            <label for="con_correo">Correo</label>
            <input id="con_correo" name="con_correo" type="email" />
            <div class="error" data-error-for="con_correo"></div>
          </div>
          <div class="col-6">
            <label for="con_empresa">Nombre de la empresa</label>
            <input id="con_empresa" name="con_empresa" maxlength="120" />
            <div class="error" data-error-for="con_empresa"></div>
          </div>
          <div class="col-6">
            <label for="con_ocupacion">Ocupación</label>
            <input id="con_ocupacion" name="con_ocupacion" maxlength="80" />
            <div class="error" data-error-for="con_ocupacion"></div>
          </div>
          <div class="col-4">
            <label for="con_anios">Años de servicio</label>
            <input id="con_anios" name="con_anios" inputmode="numeric" pattern="^\d{1,2}$" />
            <div class="error" data-error-for="con_anios"></div>
          </div>
          <div class="col-4">
            <label for="con_tel">Teléfono / Celular</label>
            <input id="con_tel" name="con_tel" inputmode="tel" />
            <div class="error" data-error-for="con_tel"></div>
          </div>
          <div class="col-4">
            <label for="con_salario">Salario (USD)</label>
            <input id="con_salario" name="con_salario" inputmode="decimal" placeholder="0.00" />
            <div class="error" data-error-for="con_salario"></div>
          </div>
          <div class="col-12">
            <label for="con_direccion">Dirección</label>
            <textarea id="con_direccion" name="con_direccion" maxlength="300"></textarea>
            <div class="error" data-error-for="con_direccion"></div>
          </div>
          <div class="col-6">
            <label for="con_otros_ingresos">Independiente y/o otros ingresos</label>
            <input id="con_otros_ingresos" name="con_otros_ingresos" maxlength="140" />
            <div class="error" data-error-for="con_otros_ingresos"></div>
          </div>
          <div class="col-6">
            <label for="con_trabajo_anterior">Trabajo anterior (si tiene menos de 2 años)</label>
            <input id="con_trabajo_anterior" name="con_trabajo_anterior" maxlength="140" />
            <div class="error" data-error-for="con_trabajo_anterior"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 4 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="4">
        <div class="sectionTitle">
          <div>
            <h2>E. Referencias</h2>
            <p>2 personales (no parientes) y 2 familiares (que no vivan con usted).</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-12"><div class="chip done"><strong>Personales (2)</strong></div></div>
          <div class="col-6">
            <label for="refp1_nombre">Personal 1 - Nombre completo *</label>
            <input id="refp1_nombre" name="refp1_nombre" required maxlength="90" />
            <div class="error" data-error-for="refp1_nombre"></div>
          </div>
          <div class="col-6">
            <label for="refp1_cel">Personal 1 - Celular *</label>
            <input id="refp1_cel" name="refp1_cel" required inputmode="tel" />
            <div class="error" data-error-for="refp1_cel"></div>
          </div>
          <div class="col-6">
            <label for="refp1_dir_res">Personal 1 - Dirección residencial</label>
            <input id="refp1_dir_res" name="refp1_dir_res" maxlength="140" />
            <div class="error" data-error-for="refp1_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="refp1_dir_lab">Personal 1 - Lugar donde Labora (Nombre de la empresa/Ministerio)</label>
            <input id="refp1_dir_lab" name="refp1_dir_lab" maxlength="140" />
            <div class="error" data-error-for="refp1_dir_lab"></div>
          </div>
          <div class="col-6">
            <label for="refp2_nombre">Personal 2 - Nombre completo</label>
            <input id="refp2_nombre" name="refp2_nombre" maxlength="90" />
            <div class="error" data-error-for="refp2_nombre"></div>
          </div>
          <div class="col-6">
            <label for="refp2_cel">Personal 2 - Celular</label>
            <input id="refp2_cel" name="refp2_cel" inputmode="tel" />
            <div class="error" data-error-for="refp2_cel"></div>
          </div>
          <div class="col-6">
            <label for="refp2_dir_res">Personal 2 - Dirección residencial</label>
            <input id="refp2_dir_res" name="refp2_dir_res" maxlength="140" />
            <div class="error" data-error-for="refp2_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="refp2_dir_lab">Personal 2 - Lugar donde Labora (Nombre de la empresa/Ministerio)</label>
            <input id="refp2_dir_lab" name="refp2_dir_lab" maxlength="140" />
            <div class="error" data-error-for="refp2_dir_lab"></div>
          </div>
          <div class="col-12" style="margin-top:6px"><div class="chip done"><strong>Familiares (2)</strong></div></div>
          <div class="col-6">
            <label for="reff1_nombre">Familiar 1 - Nombre completo</label>
            <input id="reff1_nombre" name="reff1_nombre" maxlength="90" />
            <div class="error" data-error-for="reff1_nombre"></div>
          </div>
          <div class="col-6">
            <label for="reff1_cel">Familiar 1 - Celular</label>
            <input id="reff1_cel" name="reff1_cel" inputmode="tel" />
            <div class="error" data-error-for="reff1_cel"></div>
          </div>
          <div class="col-6">
            <label for="reff1_dir_res">Familiar 1 - Dirección residencial</label>
            <input id="reff1_dir_res" name="reff1_dir_res" maxlength="140" />
            <div class="error" data-error-for="reff1_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="reff1_dir_lab">Familiar 1 - Lugar donde Labora (Nombre de la empresa/Ministerio)</label>
            <input id="reff1_dir_lab" name="reff1_dir_lab" maxlength="140" />
            <div class="error" data-error-for="reff1_dir_lab"></div>
          </div>
          <div class="col-6">
            <label for="reff2_nombre">Familiar 2 - Nombre completo</label>
            <input id="reff2_nombre" name="reff2_nombre" maxlength="90" />
            <div class="error" data-error-for="reff2_nombre"></div>
          </div>
          <div class="col-6">
            <label for="reff2_cel">Familiar 2 - Celular</label>
            <input id="reff2_cel" name="reff2_cel" inputmode="tel" />
            <div class="error" data-error-for="reff2_cel"></div>
          </div>
          <div class="col-6">
            <label for="reff2_dir_res">Familiar 2 - Dirección residencial</label>
            <input id="reff2_dir_res" name="reff2_dir_res" maxlength="140" />
            <div class="error" data-error-for="reff2_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="reff2_dir_lab">Familiar 2 - Lugar donde Labora (Nombre de la empresa/Ministerio)</label>
            <input id="reff2_dir_lab" name="reff2_dir_lab" maxlength="140" />
            <div class="error" data-error-for="reff2_dir_lab"></div>
          </div>
          <div class="col-12" style="margin-top:14px">
            <label class="d-block mb-2"><strong>Firma con el dedo (obligatorio)</strong></label>
            <p class="subtitle mb-2" style="font-size:12px;color:var(--muted)">Firme en todo el recuadro con el dedo o el mouse. Luego confirme abajo.</p>
            <div class="signature-wrap" style="border:2px solid rgba(255,255,255,.2);border-radius:12px;background:rgba(0,0,0,.2);touch-action:none;overflow:hidden;">
              <img id="tracingBackgroundFin" class="signature-trace-bg d-none" src="" alt="Firma guía para calcar">
              <canvas id="firmaCanvas" width="500" height="180" style="display:block;width:100%;height:180px;cursor:crosshair;border-radius:10px;touch-action:none;"></canvas>
            </div>
            <div style="margin-top:8px;">
              <button type="button" id="btnLimpiarFirma" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:var(--text);border:1px solid var(--line);">Limpiar firma</button>
            </div>
            <input type="hidden" id="firmaData" name="firma" />
            <div class="error" data-error-for="firma"></div>
          </div>
          <div id="firmantesAdicionalesContainer" class="col-12" style="margin-top:16px"></div>
          <div class="col-12" style="margin-top:8px">
            <button type="button" id="btnAgregarFirmante" class="btn btn-sm" style="background:var(--accent);color:#fff;border:0;">Agregar firma de codeudor</button>
          </div>
          <div class="col-12" style="margin-top:14px">
            <div class="consent-text" style="font-size:11px;line-height:1.4;color:var(--muted);margin-bottom:12px;padding:12px;background:rgba(0,0,0,.2);border-radius:10px;border:1px solid var(--line);max-height:200px;overflow-y:auto;">
              <p style="margin:0 0 10px 0;">Con la firma de esta solicitud, autorizo a PANAMA CAR RENTAL, S.A., MULTIBANK, INC., THE BANK OF NOVA SCOTIA (PANAMÁ), S.A., BANCO GENERAL, S.A., GLOBAL BANK CORPORATION, BAC International Bank, Inc., BANISTMO, S.A., BANCO DELTA, S.A., BANESCO (Panamá), S.A., BANISI, S.A., MULTIFINANCIAMIENTOS, S.A., FOSTRIAN Apoyo Financiera, CORPORACION DE CREDITO, S.A., CORPORACION DE FINANZAS DEL PAIS, S.A., FINANCIERA PACIFICO, DAVIVIENDA, ALIADO LEASING, CENTRO FINANCIERO EMPRESARIAL, SUMA FINANCIERA; a solicitar, consultar, recopilar, transmitir y revelar cualquier información, datos y documentos brindados en esta solicitud; se trate, comparta, transfiera, intercambie y utilice con terceros, ya sea que se concluya o no la adquisición del producto o servicio.</p>
              <p style="margin:0;">En cumplimiento de lo establecido en la Ley 81 de 2019 de Protección de Datos Personales, le comunicamos que los datos que usted nos facilite quedarán incorporados y serán tratados en nuestra base de datos con el fin de poderle prestar nuevos servicios, así como para mantenerle informado sobre temas relacionados con la empresa y sus servicios. Por este medio exonero expresamente a PANAMA CAR RENTAL, S.A. y/o a sus afiliadas, empleados, ejecutivos, dignatarios y apoderados, de cualquier consecuencia o responsabilidad resultante del ejercicio que ustedes hagan el derecho a solicitar o suministrar información o por razón de cualquier autorización de la presente.</p>
            </div>
            <label class="chip" style="display:flex;gap:10px;align-items:center;justify-content:flex-start">
              <input type="checkbox" id="acepta" name="acepta" required />
              Confirmo que la información es correcta y autorizo el uso para análisis de crédito (obligatorio) *
            </label>
            <div class="error" data-error-for="acepta"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 5 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="5">
        <div class="sectionTitle">
          <div>
            <h2>F. Adjuntos</h2>
            <p>Puede adjuntar uno o varios documentos (opcional). Se guardan con su solicitud en el sistema y se envían por correo junto al PDF. Si usó la solapa <strong>Identificación</strong> y recortó la cédula, esa imagen también se incluye al enviar.</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-12">
            <label for="adjuntosExtraInput">Seleccionar archivos (máx. 15, hasta 10 MB c/u)</label>
            <input type="file" id="adjuntosExtraInput" name="adjuntos_extra_input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.txt,application/pdf,image/*" class="adjuntos-file-input" />
            <p class="hint" style="margin-top:6px">Formatos: PDF, imágenes (JPG, PNG, GIF), Word, Excel, texto.</p>
            <ul id="adjuntosExtraList" class="adjuntos-extra-list" aria-live="polite"></ul>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 6 / 6</div>
          <div class="navBtns">
            <button type="button" data-prev>Atrás</button>
            <button type="submit" class="primary" id="btnSubmit">Enviar</button>
          </div>
        </div>
      </fieldset>
    </form>
  </div>

  <div class="visual-dock" id="visualDock" aria-label="Configuración visual">
    <div class="visual-dock-slide">
      <div class="visual-panel" role="region" aria-labelledby="visualPanelTitle">
        <h2 class="visual-panel-title" id="visualPanelTitle">Configuración visual</h2>
        <p class="visual-panel-hint">Ajuste contraste y tamaño de texto para leer con más comodidad. Se recuerda en este dispositivo.</p>
        <div class="visual-field">
          <span class="visual-label-inline">Fondo</span>
          <div class="visual-options">
            <label class="pick"><input type="radio" name="visual_theme" id="visualThemeDark" value="dark" checked /> Oscuro</label>
            <label class="pick"><input type="radio" name="visual_theme" id="visualThemeLight" value="light" /> Blanco (texto negro)</label>
          </div>
        </div>
        <div class="visual-field">
          <label class="visual-label-inline" for="visualFontSize">Tamaño de letra</label>
          <select id="visualFontSize" aria-describedby="visualPanelTitle">
            <option value="normal">Normal</option>
            <option value="lg">Grande</option>
            <option value="xl">Extra grande</option>
          </select>
        </div>
        <div class="visual-field">
          <label class="pick"><input type="checkbox" id="visualBold" /> Texto en negrita</label>
        </div>
      </div>
      <button type="button" class="visual-tab" id="visualDockTab" aria-expanded="false" title="Configuración visual">
        <span class="visual-gear" aria-hidden="true">⚙</span>
        <span class="visual-tab-label">Configuración visual</span>
      </button>
    </div>
  </div>

  <div class="id-dock" id="idDock" aria-label="Captura de identificación">
    <div class="id-dock-slide">
      <div class="id-panel" role="region" aria-labelledby="idPanelTitle">
        <h2 class="visual-panel-title" id="idPanelTitle">Captura de identificación</h2>
        <p class="visual-panel-hint">Toma una foto de cédula, recorta el documento y luego recorta la firma para calcarla al final.</p>
        <input type="file" id="idCedulaInput" accept="image/*" capture="environment" class="d-none">
        <div class="id-action-row">
          <button type="button" class="btn btn-sm btn-primary" id="idBtnTomarFoto">
            <i class="fas fa-camera me-1"></i>Tomar foto
          </button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="idBtnRecortarDoc" disabled>
            <i class="fas fa-crop-alt me-1"></i>Recortar cédula
          </button>
          <button type="button" class="btn btn-sm btn-outline-success" id="idBtnRecortarFirma" disabled>
            <i class="fas fa-signature me-1"></i>Recortar firma
          </button>
        </div>
        <div class="id-mini-preview" id="idMiniPreviewWrap">
          <small class="text-muted">Aún no hay imagen de cédula</small>
        </div>
      </div>
      <button type="button" class="id-tab" id="idDockTab" aria-expanded="false" title="Captura de identificación">
        <span class="id-tab-icon" aria-hidden="true">🪪</span>
        <span class="id-tab-label">Identificación</span>
      </button>
    </div>
  </div>

  <div id="idCropperModal" class="d-none" hidden style="display:none;position:fixed;inset:0;z-index:11000;background:rgba(2,6,23,.82);">
    <div style="max-width:960px;margin:4vh auto;background:#111827;border:1px solid rgba(255,255,255,.2);border-radius:14px;overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#1f2937;color:#fff;">
        <h5 class="mb-0 fw-bold" id="idCropperTitle"><i class="fas fa-crop-alt me-2"></i>Recortar</h5>
        <button type="button" class="btn btn-sm btn-outline-light" id="idCropCloseBtn">Cerrar</button>
      </div>
      <div style="padding:10px;background:#0b1220;max-height:60vh;overflow:auto;">
        <img id="idImageToCrop" src="" alt="Imagen para recortar" style="max-width:100%;display:block;margin:0 auto;">
      </div>
      <div class="id-crop-footer" style="padding:12px;background:#f8fafc;">
        <div class="d-flex justify-content-center gap-3 mb-2 d-none" id="idRotateActions">
          <button type="button" class="btn" id="idRotateLeftBtn" title="Girar 90° a la izquierda">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            <span class="id-rotate-cap">Izq.</span>
          </button>
          <button type="button" class="btn" id="idRotateRightBtn" title="Girar 90° a la derecha">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
            <span class="id-rotate-cap">Der.</span>
          </button>
        </div>
        <p class="small mb-3 text-center" id="idCropperHelp">Ajusta el recuadro.</p>
        <div class="d-flex justify-content-between">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="idCropCancelBtn">Cancelar</button>
          <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow" id="idCropApplyBtn">
            <i class="fas fa-check me-2"></i>Aplicar
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script>
    (function(){
      var TOKEN_LINK = "<?php echo $tokenLink !== '' ? addslashes($tokenLink) : ''; ?>";
      var STORAGE_KEY = "baes_financiamiento_wizard_v1" + (TOKEN_LINK ? "_t_" + TOKEN_LINK.substring(0,8) : "");
      var API_URL = <?php echo $apiUrlConfig !== '' ? json_encode($apiUrlConfig) : 'null'; ?>;
      if (!API_URL) {
        API_URL = (function(){
          var path = window.location.pathname;
          var base = path.replace(/\/financiamiento\/?.*$/, "") || "/";
          return window.location.origin + (base === "/" ? "" : base) + "/api/solicitud_publica.php";
        })();
      }

      const form = document.getElementById("wizardForm");
      const fieldsets = Array.from(form.querySelectorAll("fieldset"));
      const stepChips = document.getElementById("stepChips");
      const progressBar = document.getElementById("progressBar");
      const saveState = document.getElementById("saveState").querySelector("span");
      const toast = document.getElementById("toast");
      const btnSaveExit = document.getElementById("btnSaveExit");
      const btnClear = document.getElementById("btnClear");
      const btnSubmit = document.getElementById("btnSubmit");

      const stepLabels = ["A. Cliente", "B. Dirección", "C. Laboral", "D. Cónyuge", "E. Referencias", "F. Adjuntos"];
      let step = 0;
      let toastTimer = null;

      // Firma: canvas principal (todo el recuadro usable; botón Limpiar fuera del área de firma)
      var canvas = document.getElementById("firmaCanvas");
      var firmaDataInput = document.getElementById("firmaData");
      var btnLimpiarFirma = document.getElementById("btnLimpiarFirma");
      var tracingBackgroundFin = document.getElementById("tracingBackgroundFin");
      function signatureInkColor(){
        try{
          var v = getComputedStyle(document.documentElement).getPropertyValue("--ink-signature").trim();
          return v || "#eaf0ff";
        }catch(e){ return "#eaf0ff"; }
      }
      function setupSignatureCanvas(canvasEl, dataInput){
        if(!canvasEl || !canvasEl.getContext) return;
        var ctx = canvasEl.getContext("2d");
        var drawing = false, lastX = 0, lastY = 0;
        ctx.lineWidth = 2;
        ctx.lineCap = "round";
        function getPos(e){
          var r = canvasEl.getBoundingClientRect();
          var scaleX = canvasEl.width / r.width, scaleY = canvasEl.height / r.height;
          var clientX = e.touches ? e.touches[0].clientX : e.clientX;
          var clientY = e.touches ? e.touches[0].clientY : e.clientY;
          return { x: (clientX - r.left) * scaleX, y: (clientY - r.top) * scaleY };
        }
        function start(e){
          if (!e.touches) e.preventDefault();
          drawing = true;
          ctx.strokeStyle = signatureInkColor();
          var p = getPos(e);
          lastX = p.x;
          lastY = p.y;
        }
        function move(e){ e.preventDefault(); if (!drawing) return; var p = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.stroke(); lastX = p.x; lastY = p.y; }
        function end(e){ if (!e.changedTouches) e.preventDefault(); drawing = false; if (dataInput) dataInput.value = canvasEl.toDataURL("image/png").replace(/^data:image\/png;base64,/, ""); }
        canvasEl.addEventListener("mousedown", start);
        canvasEl.addEventListener("mousemove", move);
        canvasEl.addEventListener("mouseup", end);
        canvasEl.addEventListener("mouseleave", end);
        canvasEl.addEventListener("touchstart", start, { passive: true });
        canvasEl.addEventListener("touchmove", move, { passive: false });
        canvasEl.addEventListener("touchend", end, { passive: true });
      }
      if (canvas) setupSignatureCanvas(canvas, firmaDataInput);
      if (btnLimpiarFirma) btnLimpiarFirma.addEventListener("click", function(){
        if (canvas) { var ctx = canvas.getContext("2d"); ctx.clearRect(0, 0, canvas.width, canvas.height); }
        if (firmaDataInput) firmaDataInput.value = "";
      });

      // Captura de identificación (cédula + firma para calcar)
      var idDock = document.getElementById("idDock");
      var idDockTab = document.getElementById("idDockTab");
      var idCedulaInput = document.getElementById("idCedulaInput");
      var idBtnTomarFoto = document.getElementById("idBtnTomarFoto");
      var idBtnRecortarDoc = document.getElementById("idBtnRecortarDoc");
      var idBtnRecortarFirma = document.getElementById("idBtnRecortarFirma");
      var idMiniPreviewWrap = document.getElementById("idMiniPreviewWrap");
      var idCropperModal = document.getElementById("idCropperModal");
      var idImageToCrop = document.getElementById("idImageToCrop");
      var idCropperTitle = document.getElementById("idCropperTitle");
      var idCropperHelp = document.getElementById("idCropperHelp");
      var idRotateActions = document.getElementById("idRotateActions");
      var idRotateLeftBtn = document.getElementById("idRotateLeftBtn");
      var idRotateRightBtn = document.getElementById("idRotateRightBtn");
      var idCropApplyBtn = document.getElementById("idCropApplyBtn");
      var idCropCancelBtn = document.getElementById("idCropCancelBtn");
      var idCropCloseBtn = document.getElementById("idCropCloseBtn");

      var idOriginalImageSrc = "";
      var idDocCroppedData = "";
      var extraAdjuntosList = [];
      var adjuntosExtraInput = document.getElementById("adjuntosExtraInput");
      var adjuntosExtraListEl = document.getElementById("adjuntosExtraList");

      function base64ToFile(b64, name, mime){
        var bin = atob(b64);
        var arr = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        return new File([arr], name, { type: mime || "application/octet-stream" });
      }

      function renderExtraAdjuntosList(){
        if (!adjuntosExtraListEl) return;
        adjuntosExtraListEl.innerHTML = "";
        extraAdjuntosList.forEach(function(item){
          var li = document.createElement("li");
          li.className = "adjuntos-extra-item";
          var span = document.createElement("span");
          span.textContent = item.name + (item.size ? " (" + Math.round(item.size / 1024) + " KB)" : "");
          var btn = document.createElement("button");
          btn.type = "button";
          btn.textContent = "Quitar";
          btn.addEventListener("click", function(){
            extraAdjuntosList = extraAdjuntosList.filter(function(x){ return x.id !== item.id; });
            renderExtraAdjuntosList();
            saveDraft("auto");
          });
          li.appendChild(span);
          li.appendChild(btn);
          adjuntosExtraListEl.appendChild(li);
        });
      }

      function handleAdjuntosExtraInputChange(){
        if (!adjuntosExtraInput || !adjuntosExtraInput.files) return;
        var files = Array.prototype.slice.call(adjuntosExtraInput.files, 0);
        var maxN = 15;
        var maxBytes = 10 * 1024 * 1024;
        files.forEach(function(file){
          if (extraAdjuntosList.length >= maxN){
            showToast("Máximo " + maxN + " archivos.", "err");
            return;
          }
          if (file.size > maxBytes){
            showToast("Archivo demasiado grande (máx. 10 MB): " + file.name, "err");
            return;
          }
          var reader = new FileReader();
          reader.onload = function(){
            var comma = reader.result.indexOf(",");
            var b64 = comma >= 0 ? reader.result.slice(comma + 1) : reader.result;
            extraAdjuntosList.push({
              id: "f_" + Date.now() + "_" + Math.random().toString(36).slice(2, 9),
              name: file.name,
              mime: file.type || "application/octet-stream",
              size: file.size,
              dataBase64: b64,
              file: file
            });
            renderExtraAdjuntosList();
            saveDraft("auto");
          };
          reader.onerror = function(){ showToast("No se pudo leer el archivo: " + file.name, "err"); };
          reader.readAsDataURL(file);
        });
        adjuntosExtraInput.value = "";
      }
      var idOriginalObjectUrl = "";
      var idCropper = null;
      var idCropMode = "document";

      function setIdPreviewImage(src){
        if (!idMiniPreviewWrap) return;
        if (!src){
          idMiniPreviewWrap.innerHTML = '<small class="text-muted">Aún no hay imagen de cédula</small>';
          return;
        }
        idMiniPreviewWrap.innerHTML = '<img src="' + src + '" alt="Vista previa de cédula">';
      }

      function openIdCropper(mode){
        if (!idCropperModal || !idImageToCrop || !window.Cropper) {
          showToast("No se pudo abrir el recorte en este navegador.", "err");
          return;
        }
        idCropMode = mode === "signature" ? "signature" : "document";
        if (idCropMode === "document"){
          if (!idOriginalImageSrc) {
            showToast("Primero tome la foto de la cédula.", "err");
            return;
          }
          idCropperTitle.innerHTML = '<i class="fas fa-crop-alt me-2"></i>Recortar cédula';
          idCropperHelp.textContent = "Ajusta el marco para dejar solo el documento.";
          idRotateActions.classList.remove("d-none");
          idImageToCrop.src = idOriginalImageSrc;
        } else {
          var srcFirma = idDocCroppedData || idOriginalImageSrc;
          if (!srcFirma) {
            showToast("Primero capture y recorte la cédula.", "err");
            return;
          }
          idCropperTitle.innerHTML = '<i class="fas fa-signature me-2"></i>Recortar firma';
          idCropperHelp.textContent = "Ajusta el recorte para que quede solo la firma.";
          idRotateActions.classList.add("d-none");
          idImageToCrop.src = srcFirma;
        }

        idCropperModal.hidden = false;
        idCropperModal.style.display = "block";
        idCropperModal.classList.remove("d-none");
        idImageToCrop.onload = function(){
          if (idCropper) {
            idCropper.destroy();
            idCropper = null;
          }
          idCropper = new Cropper(idImageToCrop, {
            viewMode: 1,
            autoCropArea: idCropMode === "document" ? 0.9 : 0.2,
            guides: true,
            background: false,
            responsive: true,
            rotatable: true
          });
        };
        idImageToCrop.onerror = function(){
          showToast("No se pudo cargar la imagen para recortar.", "err");
          closeIdCropper();
        };
      }

      function closeIdCropper(){
        if (idCropper) {
          idCropper.destroy();
          idCropper = null;
        }
        if (idCropperModal){
          idCropperModal.classList.add("d-none");
          idCropperModal.hidden = true;
          idCropperModal.style.display = "none";
        }
      }

      if (idBtnTomarFoto && idCedulaInput) {
        idBtnTomarFoto.addEventListener("click", function(){ idCedulaInput.click(); });
      }
      if (idCedulaInput) {
        idCedulaInput.addEventListener("change", function(e){
          var file = e.target.files && e.target.files[0];
          if (!file) return;
          if (!file.type || file.type.indexOf("image/") !== 0) {
            showToast("Seleccione una imagen válida.", "err");
            return;
          }
          if (idOriginalObjectUrl) {
            try { URL.revokeObjectURL(idOriginalObjectUrl); } catch (err) {}
            idOriginalObjectUrl = "";
          }
          try {
            idOriginalObjectUrl = URL.createObjectURL(file);
            idOriginalImageSrc = idOriginalObjectUrl;
            idDocCroppedData = "";
            setIdPreviewImage(idOriginalImageSrc);
            if (idBtnRecortarDoc) idBtnRecortarDoc.disabled = false;
            if (idBtnRecortarFirma) idBtnRecortarFirma.disabled = false;
            openIdCropper("document");
          } catch (err) {
            var reader = new FileReader();
            reader.onload = function(ev){
              idOriginalImageSrc = String(ev.target && ev.target.result || "");
              idDocCroppedData = "";
              setIdPreviewImage(idOriginalImageSrc);
              if (idBtnRecortarDoc) idBtnRecortarDoc.disabled = false;
              if (idBtnRecortarFirma) idBtnRecortarFirma.disabled = false;
              openIdCropper("document");
            };
            reader.readAsDataURL(file);
          }
        });
      }
      if (idBtnRecortarDoc) idBtnRecortarDoc.addEventListener("click", function(){ openIdCropper("document"); });
      if (idBtnRecortarFirma) idBtnRecortarFirma.addEventListener("click", function(){ openIdCropper("signature"); });
      if (idRotateLeftBtn) idRotateLeftBtn.addEventListener("click", function(){ if (idCropper) idCropper.rotate(-90); });
      if (idRotateRightBtn) idRotateRightBtn.addEventListener("click", function(){ if (idCropper) idCropper.rotate(90); });
      if (idCropCancelBtn) idCropCancelBtn.addEventListener("click", closeIdCropper);
      if (idCropCloseBtn) idCropCloseBtn.addEventListener("click", closeIdCropper);
      if (idCropperModal) {
        idCropperModal.addEventListener("click", function(e){
          if (e.target === idCropperModal) closeIdCropper();
        });
      }
      document.addEventListener("keydown", function(e){
        if (e.key === "Escape") closeIdCropper();
      });

      if (idCropApplyBtn) {
        idCropApplyBtn.addEventListener("click", function(){
          if (!idCropper) return;
          var cropCanvas = idCropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 });
          if (!cropCanvas) return;
          if (idCropMode === "document"){
            idDocCroppedData = cropCanvas.toDataURL("image/jpeg", 0.85);
            setIdPreviewImage(idDocCroppedData);
            showToast("Documento recortado correctamente.", "ok");
          } else {
            var firmaRecortada = cropCanvas.toDataURL("image/png");
            if (tracingBackgroundFin) {
              tracingBackgroundFin.src = firmaRecortada;
              tracingBackgroundFin.classList.remove("d-none");
            }
            showToast("Firma recortada lista para calcar.", "ok");
          }
          closeIdCropper();
        });
      }

      // Firmantes adicionales
      var firmantesAdicionales = [];
      var firmantesContainer = document.getElementById("firmantesAdicionalesContainer");
      var btnAgregarFirmante = document.getElementById("btnAgregarFirmante");
      function addFirmanteBlock(){
        if (!firmantesContainer) return;
        if (firmantesContainer.querySelector(".firmante-adicional-block")){
          showToast("Ya se agregó la firma de codeudor.", "err");
          return;
        }
        var nombre = "Codeudor";
        var id = "fa_" + Date.now() + "_" + Math.random().toString(36).slice(2,6);
        var block = document.createElement("div");
        block.className = "firmante-adicional-block";
        block.style.cssText = "margin-top:14px;padding:14px;border:1px solid var(--line);border-radius:12px;background:rgba(0,0,0,.15);";
        var lbl = document.createElement("div");
        lbl.className = "d-block";
        lbl.style.marginBottom = "8px";
        lbl.innerHTML = "<strong>Firma de codeudor</strong>";
        block.appendChild(lbl);
        var nombreInput = document.createElement("input");
        nombreInput.type = "hidden";
        nombreInput.name = "fa_nombre_" + id;
        nombreInput.setAttribute("data-fa-nombre", "");
        nombreInput.value = nombre;
        block.appendChild(nombreInput);
        var wrap = document.createElement("div");
        wrap.style.cssText = "border:2px solid rgba(255,255,255,.2);border-radius:8px;margin:8px 0;overflow:hidden;touch-action:none;";
        var can = document.createElement("canvas");
        can.setAttribute("data-fa-canvas", "");
        can.width = 500;
        can.height = 140;
        can.style.cssText = "display:block;width:100%;height:140px;cursor:crosshair;";
        wrap.appendChild(can);
        block.appendChild(wrap);
        var firmaInput = document.createElement("input");
        firmaInput.type = "hidden";
        firmaInput.name = "fa_firma_" + id;
        firmaInput.setAttribute("data-fa-firma", "");
        block.appendChild(firmaInput);
        var btnWrap = document.createElement("div");
        btnWrap.style.marginTop = "6px";
        var btnLimpiar = document.createElement("button");
        btnLimpiar.type = "button";
        btnLimpiar.className = "btn btn-sm btn-limpiar-fa";
        btnLimpiar.setAttribute("data-fa-id", id);
        btnLimpiar.style.cssText = "background:rgba(255,255,255,.15);color:var(--text);border:1px solid var(--line);margin-right:8px;";
        btnLimpiar.textContent = "Limpiar";
        var btnQuitar = document.createElement("button");
        btnQuitar.type = "button";
        btnQuitar.className = "btn btn-sm btn-quitar-fa";
        btnQuitar.setAttribute("data-fa-id", id);
        btnQuitar.style.cssText = "background:var(--danger);color:#fff;border:0;";
        btnQuitar.textContent = "Quitar firma de codeudor";
        btnWrap.appendChild(btnLimpiar);
        btnWrap.appendChild(btnQuitar);
        block.appendChild(btnWrap);
        firmantesContainer.appendChild(block);
        if (btnAgregarFirmante) btnAgregarFirmante.disabled = true;
        setupSignatureCanvas(can, firmaInput);
        can.addEventListener("mouseup", function(){ firmaInput.value = can.toDataURL("image/png").replace(/^data:image\/png;base64,/, ""); });
        can.addEventListener("touchend", function(){ firmaInput.value = can.toDataURL("image/png").replace(/^data:image\/png;base64,/, ""); });
        block.querySelector(".btn-limpiar-fa").addEventListener("click", function(){
          var ctx = can.getContext("2d");
          ctx.clearRect(0, 0, can.width, can.height);
          firmaInput.value = "";
        });
        block.querySelector(".btn-quitar-fa").addEventListener("click", function(){
          block.remove();
          if (btnAgregarFirmante) btnAgregarFirmante.disabled = false;
        });
      }
      if (btnAgregarFirmante) btnAgregarFirmante.addEventListener("click", addFirmanteBlock);

      function showToast(msg, type){
        toast.textContent = msg;
        toast.className = "toast show" + (type === "ok" ? " ok" : type === "err" ? " err" : "");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function(){ toast.classList.remove("show"); }, type ? 3500 : 1800);
      }

      function formatMoneyLike(v){
        if(v == null) return "";
        var cleaned = String(v).replace(/[^\d.]/g, "");
        var parts = cleaned.split(".");
        var safe = parts.length <= 2 ? cleaned : (parts[0] + "." + parts.slice(1).join(""));
        return safe;
      }

      function makeChips(){
        stepChips.innerHTML = "";
        stepLabels.forEach(function(label, idx){
          var el = document.createElement("div");
          el.className = "chip";
          el.dataset.chip = idx;
          el.innerHTML = "<strong>" + (idx+1) + "</strong> " + label;
          el.addEventListener("click", function(){
            if(idx <= step){
              goTo(idx);
            } else {
              var ok = true;
              for(var s = step; s < idx; s++){
                if(!validateStep(s)){ ok = false; break; }
                markStepDone(s, true);
              }
              if(ok) goTo(idx);
            }
          });
          stepChips.appendChild(el);
        });
      }

      function setChipState(){
        stepLabels.forEach(function(_, idx){
          var chip = stepChips.querySelector("[data-chip=\"" + idx + "\"]");
          chip.classList.remove("active");
          if(idx === step) chip.classList.add("active");
        });
      }

      function markStepDone(s, done){
        var chip = stepChips.querySelector("[data-chip=\"" + s + "\"]");
        if(!chip) return;
        chip.classList.toggle("done", !!done);
      }

      function calcProgress(){
        var pct = Math.round((step / (fieldsets.length - 1)) * 100);
        progressBar.style.width = pct + "%";
      }

      function goTo(next){
        fieldsets[step].classList.remove("active");
        step = Math.max(0, Math.min(fieldsets.length - 1, next));
        fieldsets[step].classList.add("active");
        setChipState();
        calcProgress();
        window.scrollTo({top:0, behavior:"smooth"});
        saveDraft("auto");
        updateStep0NextButtonState();
      }

      function readFormToObject(){
        var data = {};
        form.querySelectorAll("input, select, textarea").forEach(function(el){
          if(!el.name) return;
          if(el.type === "file") return;
          if(el.type === "checkbox"){
            data[el.name] = el.checked;
            return;
          }
          if(el.type === "radio"){
            if(!(el.name in data)) data[el.name] = "";
            if(el.checked) data[el.name] = el.value;
            return;
          }
          data[el.name] = el.value;
        });
        data.__meta = { step: step, savedAt: new Date().toISOString() };
        return data;
      }

      function fillFormFromObject(data){
        if(!data) return;
        form.querySelectorAll("input, select, textarea").forEach(function(el){
          if(!el.name) return;
          var v = data[el.name];
          if(el.type === "checkbox"){
            el.checked = !!v;
            return;
          }
          if(el.type === "radio"){
            el.checked = (String(v || "") === el.value);
            return;
          }
          if(v !== undefined && v !== null) el.value = String(v);
        });
        if(data.__meta && typeof data.__meta.step === "number"){
          step = Math.max(0, Math.min(fieldsets.length - 1, data.__meta.step));
        }
        var nacEl = form.elements["cliente_nacimiento"];
        if (nacEl && data.cliente_nacimiento != null && data.cliente_nacimiento !== undefined){
          var rawN = String(data.cliente_nacimiento).trim();
          if (/^\d{4}-\d{2}-\d{2}$/.test(rawN)){
            nacEl.value = rawN.slice(5, 7) + "/" + rawN.slice(8, 10) + "/" + rawN.slice(0, 4);
          }
        }
        syncClienteNacimientoVisual();
        var conNacEl = form.elements["con_nacimiento"];
        if (conNacEl && data.con_nacimiento != null && data.con_nacimiento !== undefined){
          var rawC = String(data.con_nacimiento).trim();
          if (/^\d{4}-\d{2}-\d{2}$/.test(rawC)){
            conNacEl.value = rawC.slice(5, 7) + "/" + rawC.slice(8, 10) + "/" + rawC.slice(0, 4);
          }
        }
        syncConyugeNacimientoVisual();
        if (data.__meta){
          if (data.__meta.imagen_cedula){
            idDocCroppedData = data.__meta.imagen_cedula;
            setIdPreviewImage(idDocCroppedData);
            if (idBtnRecortarDoc) idBtnRecortarDoc.disabled = false;
            if (idBtnRecortarFirma) idBtnRecortarFirma.disabled = false;
          } else {
            idDocCroppedData = "";
            setIdPreviewImage("");
            if (idBtnRecortarDoc) idBtnRecortarDoc.disabled = true;
            if (idBtnRecortarFirma) idBtnRecortarFirma.disabled = true;
          }
          if (Array.isArray(data.__meta.adjuntos_extra)){
            extraAdjuntosList = data.__meta.adjuntos_extra.map(function(row, idx){
              return {
                id: "r_" + idx + "_" + Date.now(),
                name: row.name || "archivo",
                mime: row.mime || "",
                size: 0,
                dataBase64: row.data || "",
                file: null
              };
            });
            renderExtraAdjuntosList();
          } else {
            extraAdjuntosList = [];
            renderExtraAdjuntosList();
          }
        }
      }

      function saveDraft(mode){
        var payload = readFormToObject();
        var meta = Object.assign({}, payload.__meta || {}, { step: step, savedAt: new Date().toISOString() });
        if (idDocCroppedData) meta.imagen_cedula = idDocCroppedData;
        else delete meta.imagen_cedula;
        meta.adjuntos_extra = extraAdjuntosList.map(function(it){
          return { name: it.name, mime: it.mime, data: it.dataBase64 };
        });
        payload.__meta = meta;
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
          saveState.textContent = mode === "manual" ? "Guardado" : "Auto-guardado";
        } catch(e) {
          showToast("No se pudo guardar todo en este dispositivo (demasiado volumen de adjuntos). Quite algunos archivos o una imagen muy grande.", "err");
        }
      }

      function aplicarCompatibilidadDireccion(payload){
        var provincia = String(payload.provincia || "").trim();
        var distrito = String(payload.distrito || "").trim();
        var corregimiento = String(payload.corregimiento || "").trim();
        var barriada = String(payload.barriada || "").trim();
        var casaEdif = String(payload.casa_edif || "").trim();
        var numeroCasaApto = String(payload.numero_casa_apto || "").trim();
        var direccion = String(payload.direccion || "").trim();

        payload.prov_dist_corr = [provincia, distrito, corregimiento].filter(Boolean).join(", ");
        payload.barriada_calle_casa = [barriada, direccion].filter(Boolean).join(" - ");
        payload.edificio_apto = [casaEdif, numeroCasaApto].filter(Boolean).join(", ");
      }

      function loadDraft(){
        try {
          var raw = localStorage.getItem(STORAGE_KEY);
          if(!raw) return null;
          return JSON.parse(raw);
        } catch(e){ return null; }
      }

      function clearDraft(){
        localStorage.removeItem(STORAGE_KEY);
        saveState.textContent = "Sin guardar";
        showToast("Progreso borrado");
      }

      function setError(name, msg){
        var box = null;
        form.querySelectorAll("[data-error-for]").forEach(function(el){
          if(el.getAttribute("data-error-for") === name) box = el;
        });
        if(!box) return;
        box.textContent = msg || "";
        box.style.display = msg ? "block" : "none";
      }

      function clearErrorsInStep(stepIndex){
        fieldsets[stepIndex].querySelectorAll(".error").forEach(function(e){
          e.textContent = "";
          e.style.display = "none";
        });
      }

      var NACIMIENTO_HINT_DEFAULT = "Digite solo números; formato MM/DD/YYYY (mes/día/año). La edad debe estar entre 18 y 100 años.";
      var CON_NACIMIENTO_HINT_DEFAULT = "Opcional. Formato MM/DD/YYYY (mes/día/año). La edad se calcula sola; sin límite de edad.";

      function formatNacimientoDigits(digits){
        digits = String(digits || "").replace(/\D/g, "").slice(0, 8);
        if (digits.length <= 2) return digits;
        if (digits.length <= 4) return digits.slice(0, 2) + "/" + digits.slice(2);
        return digits.slice(0, 2) + "/" + digits.slice(2, 4) + "/" + digits.slice(4);
      }

      function parseUsDateFromString(str){
        var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(str || "").trim());
        if (!m) return null;
        var month = parseInt(m[1], 10);
        var day = parseInt(m[2], 10);
        var year = parseInt(m[3], 10);
        if (month < 1 || month > 12) return null;
        var d = new Date(year, month - 1, day);
        if (d.getFullYear() !== year || d.getMonth() !== month - 1 || d.getDate() !== day) return null;
        return d;
      }

      function dateOnlyLocal(d){
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
      }

      function yearsSinceBirth(birthDate){
        var today = new Date();
        var y = today.getFullYear() - birthDate.getFullYear();
        var mo = today.getMonth() - birthDate.getMonth();
        if (mo < 0 || (mo === 0 && today.getDate() < birthDate.getDate())) y--;
        return y;
      }

      function analyzeClienteNacimiento(raw){
        var s = String(raw || "").trim();
        if (!s) return { status: "empty" };
        var digits = s.replace(/\D/g, "");
        if (s.length < 10 || digits.length < 8) return { status: "partial" };
        var d = parseUsDateFromString(s);
        if (!d) return { status: "bad" };
        var todayD = dateOnlyLocal(new Date());
        var birthD = dateOnlyLocal(d);
        if (birthD.getTime() > todayD.getTime()) return { status: "future" };
        var age = yearsSinceBirth(d);
        if (age < 18) return { status: "minor", age: age };
        if (age > 100) return { status: "senior", age: age };
        return { status: "ok", age: age };
      }

      /** Fecha cónyuge: opcional; sin reglas de edad mínima/máxima. */
      function analyzeConyugeNacimiento(raw){
        var s = String(raw || "").trim();
        if (!s) return { status: "empty" };
        var digits = s.replace(/\D/g, "");
        if (s.length < 10 || digits.length < 8) return { status: "partial" };
        var d = parseUsDateFromString(s);
        if (!d) return { status: "bad" };
        var todayD = dateOnlyLocal(new Date());
        var birthD = dateOnlyLocal(d);
        if (birthD.getTime() > todayD.getTime()) return { status: "future" };
        var age = yearsSinceBirth(d);
        return { status: "ok", age: age };
      }

      function updateStep0NextButtonState(){
        var fs0 = fieldsets[0];
        var nextBtn = fs0 ? fs0.querySelector("button[data-next]") : null;
        if (!nextBtn) return;
        if (step !== 0){
          nextBtn.disabled = false;
          nextBtn.removeAttribute("title");
          return;
        }
        var st = analyzeClienteNacimiento(form.elements["cliente_nacimiento"] && form.elements["cliente_nacimiento"].value);
        var ok = st.status === "ok";
        nextBtn.disabled = !ok;
        if (!ok){
          var t = "Indique una fecha válida (MM/DD/YYYY): entre 18 y 100 años.";
          if (st.status === "empty") t = "Indique la fecha de nacimiento (MM/DD/YYYY).";
          if (st.status === "partial") t = "Complete la fecha en formato MM/DD/YYYY (mes/día/año).";
          if (st.status === "bad") t = "Fecha inválida. Use MM/DD/YYYY (mes/día/año).";
          if (st.status === "future") t = "La fecha no puede ser futura.";
          if (st.status === "minor") t = "Debe ser mayor de 18 años.";
          if (st.status === "senior") t = "La edad no puede superar 100 años.";
          nextBtn.title = t;
        } else {
          nextBtn.removeAttribute("title");
        }
      }

      function syncClienteNacimientoVisual(){
        var inp = form.elements["cliente_nacimiento"];
        var edadEl = form.elements["cliente_edad"];
        var hint = document.getElementById("clienteNacimientoHint");
        if (!inp || !edadEl) return;
        var st = analyzeClienteNacimiento(inp.value);
        setError("cliente_nacimiento", "");
        setError("cliente_edad", "");
        inp.classList.remove("input-format-ok", "input-format-bad");
        edadEl.classList.remove("input-format-ok", "input-format-bad");
        if (hint){
          hint.classList.remove("err", "ok");
          hint.textContent = NACIMIENTO_HINT_DEFAULT;
        }
        if (st.status === "empty"){
          edadEl.value = "";
          updateStep0NextButtonState();
          return;
        }
        if (st.status === "partial"){
          edadEl.value = "";
          updateStep0NextButtonState();
          return;
        }
        if (st.status === "bad" || st.status === "future" || st.status === "minor" || st.status === "senior"){
          inp.classList.add("input-format-bad");
          if (typeof st.age === "number") edadEl.value = String(st.age);
          else edadEl.value = "";
          if (hint){
            hint.classList.add("err");
            if (st.status === "bad") hint.textContent = "Use el formato MM/DD/YYYY (mes/día/año) y una fecha válida.";
            if (st.status === "future") hint.textContent = "La fecha de nacimiento no puede ser futura.";
            if (st.status === "minor") hint.textContent = "El cliente no posee la mayoría de edad (debe tener al menos 18 años).";
            if (st.status === "senior") hint.textContent = "La edad no puede superar 100 años. Verifique la fecha.";
          }
          updateStep0NextButtonState();
          return;
        }
        if (st.status === "ok"){
          edadEl.value = String(st.age);
          inp.classList.add("input-format-ok");
          edadEl.classList.add("input-format-ok");
          if (hint){
            hint.classList.add("ok");
            hint.textContent = "Fecha correcta (MM/DD/YYYY).";
          }
        }
        updateStep0NextButtonState();
      }

      function validateClienteNacimientoStep0(){
        var inp = form.elements["cliente_nacimiento"];
        var edadEl = form.elements["cliente_edad"];
        if (!inp || !edadEl) return true;
        var st = analyzeClienteNacimiento(inp.value);
        var hint = document.getElementById("clienteNacimientoHint");
        inp.classList.remove("input-format-ok", "input-format-bad");
        edadEl.classList.remove("input-format-ok", "input-format-bad");
        if (hint){
          hint.classList.remove("err", "ok");
          hint.textContent = NACIMIENTO_HINT_DEFAULT;
        }
        if (st.status === "empty"){
          setError("cliente_nacimiento", "Este campo es obligatorio.");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          return false;
        }
        if (st.status === "partial"){
          setError("cliente_nacimiento", "Complete la fecha en formato MM/DD/YYYY (mes/día/año).");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          return false;
        }
        if (st.status === "bad"){
          setError("cliente_nacimiento", "Formato o fecha inválida. Use MM/DD/YYYY (mes/día/año).");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){ hint.classList.add("err"); hint.textContent = "Use el formato MM/DD/YYYY (mes/día/año) y una fecha válida."; }
          return false;
        }
        if (st.status === "future"){
          setError("cliente_nacimiento", "La fecha de nacimiento no puede ser futura.");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){ hint.classList.add("err"); hint.textContent = "La fecha no puede ser futura."; }
          return false;
        }
        if (st.status === "minor"){
          var msg = "El cliente no posee la mayoría de edad (debe tener al menos 18 años).";
          setError("cliente_nacimiento", msg);
          setError("cliente_edad", msg);
          inp.classList.add("input-format-bad");
          edadEl.value = String(st.age);
          if (hint){ hint.classList.add("err"); hint.textContent = msg; }
          return false;
        }
        if (st.status === "senior"){
          var msg2 = "La edad no puede superar 100 años. Verifique la fecha de nacimiento.";
          setError("cliente_nacimiento", msg2);
          setError("cliente_edad", msg2);
          inp.classList.add("input-format-bad");
          edadEl.value = String(st.age);
          if (hint){ hint.classList.add("err"); hint.textContent = msg2; }
          return false;
        }
        edadEl.value = String(st.age);
        inp.classList.add("input-format-ok");
        edadEl.classList.add("input-format-ok");
        if (hint){
          hint.classList.add("ok");
          hint.textContent = "Fecha correcta (MM/DD/YYYY).";
        }
        return true;
      }

      function setupClienteNacimientoField(){
        var inp = form.elements["cliente_nacimiento"];
        if (!inp || inp.getAttribute("data-nac-bound") === "1") return;
        inp.setAttribute("data-nac-bound", "1");
        inp.addEventListener("keydown", function(e){
          if (e.ctrlKey || e.metaKey || e.altKey) return;
          var k = e.key;
          if (k === "Backspace" || k === "Delete" || k === "Tab" || k === "Enter" || (k && k.indexOf("Arrow") === 0) || k === "Home" || k === "End") return;
          if (k && k.length === 1 && !/\d/.test(k)) e.preventDefault();
        });
        inp.addEventListener("input", function(){
          var digits = inp.value.replace(/\D/g, "").slice(0, 8);
          var formatted = formatNacimientoDigits(digits);
          if (formatted !== inp.value) inp.value = formatted;
          syncClienteNacimientoVisual();
        });
        inp.addEventListener("paste", function(e){
          var t = e.clipboardData && e.clipboardData.getData("text");
          if (t == null) return;
          e.preventDefault();
          var digits = (inp.value + t).replace(/\D/g, "").slice(0, 8);
          inp.value = formatNacimientoDigits(digits);
          syncClienteNacimientoVisual();
        });
      }

      function syncConyugeNacimientoVisual(){
        var inp = form.elements["con_nacimiento"];
        var edadEl = form.elements["con_edad"];
        var hint = document.getElementById("conNacimientoHint");
        if (!inp || !edadEl) return;
        var tiene = form.elements["tiene_conyuge"] && form.elements["tiene_conyuge"].checked;
        setError("con_nacimiento", "");
        setError("con_edad", "");
        inp.classList.remove("input-format-ok", "input-format-bad");
        edadEl.classList.remove("input-format-ok", "input-format-bad");
        if (hint){
          hint.classList.remove("err", "ok");
          hint.textContent = CON_NACIMIENTO_HINT_DEFAULT;
        }
        if (!tiene){
          edadEl.value = "";
          return;
        }
        var st = analyzeConyugeNacimiento(inp.value);
        if (st.status === "empty"){
          edadEl.value = "";
          return;
        }
        if (st.status === "partial"){
          edadEl.value = "";
          return;
        }
        if (st.status === "bad" || st.status === "future"){
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){
            hint.classList.add("err");
            if (st.status === "bad") hint.textContent = "Use el formato MM/DD/YYYY (mes/día/año) y una fecha válida.";
            if (st.status === "future") hint.textContent = "La fecha de nacimiento no puede ser futura.";
          }
          return;
        }
        if (st.status === "ok"){
          edadEl.value = String(st.age);
          inp.classList.add("input-format-ok");
          edadEl.classList.add("input-format-ok");
          if (hint){
            hint.classList.add("ok");
            hint.textContent = "Fecha correcta (MM/DD/YYYY). Edad: " + st.age + " años.";
          }
        }
      }

      function validateConyugeNacimientoStep3(){
        var tiene = form.elements["tiene_conyuge"] && form.elements["tiene_conyuge"].checked;
        if (!tiene) return true;
        var inp = form.elements["con_nacimiento"];
        var edadEl = form.elements["con_edad"];
        var hint = document.getElementById("conNacimientoHint");
        if (!inp || !edadEl) return true;
        var st = analyzeConyugeNacimiento(inp.value);
        inp.classList.remove("input-format-ok", "input-format-bad");
        edadEl.classList.remove("input-format-ok", "input-format-bad");
        if (hint){
          hint.classList.remove("err", "ok");
          hint.textContent = CON_NACIMIENTO_HINT_DEFAULT;
        }
        if (st.status === "empty"){
          edadEl.value = "";
          return true;
        }
        if (st.status === "partial"){
          setError("con_nacimiento", "Complete la fecha en formato MM/DD/YYYY (mes/día/año), o deje el campo vacío.");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){ hint.classList.add("err"); hint.textContent = "Complete MM/DD/YYYY o vacíe el campo."; }
          return false;
        }
        if (st.status === "bad"){
          setError("con_nacimiento", "Formato o fecha inválida. Use MM/DD/YYYY (mes/día/año), o deje el campo vacío.");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){ hint.classList.add("err"); hint.textContent = "Fecha inválida. Use MM/DD/YYYY (mes/día/año)."; }
          return false;
        }
        if (st.status === "future"){
          setError("con_nacimiento", "La fecha de nacimiento no puede ser futura.");
          inp.classList.add("input-format-bad");
          edadEl.value = "";
          if (hint){ hint.classList.add("err"); hint.textContent = "La fecha no puede ser futura."; }
          return false;
        }
        edadEl.value = String(st.age);
        inp.classList.add("input-format-ok");
        edadEl.classList.add("input-format-ok");
        if (hint){
          hint.classList.add("ok");
          hint.textContent = "Fecha correcta (MM/DD/YYYY). Edad: " + st.age + " años.";
        }
        return true;
      }

      function setupConyugeNacimientoField(){
        var inp = form.elements["con_nacimiento"];
        var chk = form.elements["tiene_conyuge"];
        if (!inp || inp.getAttribute("data-con-nac-bound") === "1") return;
        inp.setAttribute("data-con-nac-bound", "1");
        inp.addEventListener("keydown", function(e){
          if (e.ctrlKey || e.metaKey || e.altKey) return;
          var k = e.key;
          if (k === "Backspace" || k === "Delete" || k === "Tab" || k === "Enter" || (k && k.indexOf("Arrow") === 0) || k === "Home" || k === "End") return;
          if (k && k.length === 1 && !/\d/.test(k)) e.preventDefault();
        });
        inp.addEventListener("input", function(){
          var digits = inp.value.replace(/\D/g, "").slice(0, 8);
          var formatted = formatNacimientoDigits(digits);
          if (formatted !== inp.value) inp.value = formatted;
          syncConyugeNacimientoVisual();
        });
        inp.addEventListener("paste", function(e){
          var t = e.clipboardData && e.clipboardData.getData("text");
          if (t == null) return;
          e.preventDefault();
          var digits = (inp.value + t).replace(/\D/g, "").slice(0, 8);
          inp.value = formatNacimientoDigits(digits);
          syncConyugeNacimientoVisual();
        });
        if (chk){
          chk.addEventListener("change", function(){
            syncConyugeNacimientoVisual();
          });
        }
      }

      function validateMoneyField(name, required){
        var el = form.elements[name];
        if(!el) return true;
        el.value = formatMoneyLike(el.value);
        if(required && !el.value){ setError(name, "Este campo es obligatorio."); return false; }
        if(el.value){
          var num = Number(el.value);
          if(Number.isNaN(num) || num < 0){ setError(name, "Monto inválido."); return false; }
        }
        return true;
      }

      function validateStep(stepIndex){
        clearErrorsInStep(stepIndex);
        var fs = fieldsets[stepIndex];
        var ok = true;
        var inputs = Array.prototype.filter.call(fs.querySelectorAll("input, select, textarea"), function(el){ return el.name; });
        var tieneConyuge = !!form.elements["tiene_conyuge"] && form.elements["tiene_conyuge"].checked;

        inputs.forEach(function(el){
          if(el.type === "file") return;
          if(el.name === "cliente_nacimiento" || el.name === "cliente_edad") return;
          if(el.name === "con_nacimiento" || el.name === "con_edad") return;
          if(el.name.indexOf("con_") === 0 && !tieneConyuge) return;
          if(["precio_venta","abono","empresa_salario","con_salario","vivienda_monto"].indexOf(el.name) >= 0){
            el.value = formatMoneyLike(el.value);
          }
          if(el.name.indexOf("con_") === 0){
            el.required = tieneConyuge && (el.name === "con_nombre" || el.name === "con_id");
          }
          if(!el.checkValidity()){
            ok = false;
            var msg = el.validationMessage || "Dato inválido.";
            if(el.validity.valueMissing) msg = "Este campo es obligatorio.";
            if(el.validity.typeMismatch && el.type === "email") msg = "Correo inválido.";
            if(el.validity.patternMismatch) msg = "Formato inválido.";
            setError(el.name, msg);
          } else {
            setError(el.name, "");
          }
        });

        if(stepIndex === 0) ok = validateClienteNacimientoStep0() && ok;

        if(stepIndex === 1){
          var vivienda = form.elements["vivienda"] && form.elements["vivienda"].value;
          var monto = form.elements["vivienda_monto"] && form.elements["vivienda_monto"].value;
          if((vivienda === "Alquilada" || vivienda === "Hipotecada") && !monto){
            ok = false;
            setError("vivienda_monto", "Indique el monto (alquiler o hipoteca).");
          } else {
            ok = validateMoneyField("vivienda_monto", false) && ok;
          }
        }
        if(stepIndex === 2) ok = validateMoneyField("empresa_salario", true) && ok;
        if(stepIndex === 3 && tieneConyuge){
          ok = validateConyugeNacimientoStep3() && ok;
          ok = validateMoneyField("con_salario", false) && ok;
        }
        if(stepIndex === 4){
          if(TOKEN_LINK){
            var firmaVal = (firmaDataInput && firmaDataInput.value) ? firmaDataInput.value.trim() : "";
            if(!firmaVal){ ok = false; setError("firma", "Debe firmar en el recuadro con el dedo o el mouse."); }
          }
          var acepta = form.elements["acepta"];
          if(acepta && !acepta.checked){ ok = false; setError("acepta", "Debes confirmar para continuar."); }
        }
        return ok;
      }

      function attachNavButtons(){
        form.querySelectorAll("[data-next]").forEach(function(btn){
          btn.addEventListener("click", function(){
            if(validateStep(step)){
              markStepDone(step, true);
              goTo(step + 1);
            } else {
              showToast("Revisa los campos marcados en rojo");
            }
          });
        });
        form.querySelectorAll("[data-prev]").forEach(function(btn){
          btn.addEventListener("click", function(){ goTo(step - 1); });
        });
      }

      form.addEventListener("input", function(e){
        var t = e.target;
        if(t && t.name){
          if(["precio_venta","abono","empresa_salario","con_salario","vivienda_monto"].indexOf(t.name) >= 0)
            t.value = formatMoneyLike(t.value);
          saveDraft("auto");
        }
      });
      form.addEventListener("change", function(e){
        if(e.target && e.target.name) saveDraft("auto");
        if(e.target && e.target.name === "tiene_conyuge") syncConyugeNacimientoVisual();
      });
      form.addEventListener("reset", function(){
        setTimeout(function(){
          syncClienteNacimientoVisual();
          syncConyugeNacimientoVisual();
          if (tracingBackgroundFin) {
            tracingBackgroundFin.src = "";
            tracingBackgroundFin.classList.add("d-none");
          }
          if (idOriginalObjectUrl) {
            try { URL.revokeObjectURL(idOriginalObjectUrl); } catch (err) {}
            idOriginalObjectUrl = "";
          }
          idOriginalImageSrc = "";
          idDocCroppedData = "";
          if (idCedulaInput) idCedulaInput.value = "";
          setIdPreviewImage("");
          if (idBtnRecortarDoc) idBtnRecortarDoc.disabled = true;
          if (idBtnRecortarFirma) idBtnRecortarFirma.disabled = true;
          extraAdjuntosList = [];
          renderExtraAdjuntosList();
        }, 0);
      });
      window.addEventListener("beforeunload", function(){ try{ saveDraft("auto"); }catch(e){} });

      function init(){
        setupClienteNacimientoField();
        setupConyugeNacimientoField();
        makeChips();
        attachNavButtons();
        if (adjuntosExtraInput) adjuntosExtraInput.addEventListener("change", handleAdjuntosExtraInputChange);

        var draft = loadDraft();
        if(draft){
          fillFormFromObject(draft);
          fieldsets.forEach(function(fs){ fs.classList.remove("active"); });
          fieldsets[step].classList.add("active");
          setChipState();
          calcProgress();
          saveState.textContent = "Restaurado";
          showToast("Progreso restaurado");
        } else {
          setChipState();
          calcProgress();
        }
        updateStep0NextButtonState();
        syncConyugeNacimientoVisual();

        btnSaveExit.addEventListener("click", function(){
          saveDraft("manual");
          showToast("Guardado. Puedes cerrar la pestaña.");
        });

        btnClear.addEventListener("click", function(){
          if(confirm("¿Seguro que quieres borrar el progreso guardado en este dispositivo?")){
            clearDraft();
            form.reset();
            fieldsets.forEach(function(fs){ fs.classList.remove("active"); });
            step = 0;
            fieldsets[0].classList.add("active");
            makeChips();
            setChipState();
            calcProgress();
            localStorage.removeItem(STORAGE_KEY);
          }
        });

        form.addEventListener("submit", function(e){
          e.preventDefault();

          for(var s = 0; s < fieldsets.length; s++){
            if(!validateStep(s)){
              goTo(s);
              showToast("Faltan campos obligatorios", "err");
              return;
            }
          }

          var payload = readFormToObject();
          delete payload.__meta;
          delete payload.acepta;
          aplicarCompatibilidadDireccion(payload);
          if(TOKEN_LINK) payload.token = TOKEN_LINK;
          if(firmaDataInput && firmaDataInput.value) payload.firma = firmaDataInput.value;
          var faBlocks = document.querySelectorAll(".firmante-adicional-block");
          var faList = [];
          faBlocks.forEach(function(blk){
            var nomInp = blk.querySelector("input[data-fa-nombre]");
            var firmaInp = blk.querySelector("input[data-fa-firma]");
            if (nomInp && firmaInp && nomInp.value && firmaInp.value) faList.push({ nombre: nomInp.value, firma: firmaInp.value });
          });
          if (faList.length) payload.firmantes_adicionales = JSON.stringify(faList);
          Object.keys(payload).forEach(function(k){ if (k.indexOf("fa_nombre_") === 0 || k.indexOf("fa_firma_") === 0) delete payload[k]; });
          if (idDocCroppedData) payload.imagen_cedula = idDocCroppedData;

          btnSubmit.disabled = true;
          btnSubmit.textContent = "Enviando…";

          var fd = new FormData();
          fd.append("payload", JSON.stringify(payload));
          extraAdjuntosList.forEach(function(it){
            var f = it.file;
            if (!f && it.dataBase64){
              try { f = base64ToFile(it.dataBase64, it.name, it.mime); } catch (err) { f = null; }
            }
            if (f) fd.append("adjuntos_extra[]", f, it.name);
          });

          fetch(API_URL, {
            method: "POST",
            body: fd
          })
          .then(function(r){ return r.json().then(function(data){ return { ok: r.ok, data: data }; }); })
          .then(function(result){
            if(result.ok && result.data.success){
              localStorage.removeItem(STORAGE_KEY);
              saveState.textContent = "Enviado";
              showToast(result.data.message || "Solicitud registrada correctamente.", "ok");
              form.reset();
              setTimeout(function(){
                if(confirm(result.data.message + "\n\n¿Desea enviar otra solicitud?")){
                  step = 0;
                  fieldsets.forEach(function(fs){ fs.classList.remove("active"); });
                  fieldsets[0].classList.add("active");
                  setChipState();
                  calcProgress();
                  saveState.textContent = "Sin guardar";
                }
              }, 400);
            } else {
              showToast(result.data.message || "Error al enviar. Intente de nuevo.", "err");
            }
          })
          .catch(function(){
            showToast("Error de conexión. Intente de nuevo.", "err");
          })
          .finally(function(){
            btnSubmit.disabled = false;
            btnSubmit.textContent = "Enviar";
          });
        });
      }

      function initVisualPreferences(){
        var KEY = "financiamiento_visual_prefs";
        var dock = document.getElementById("visualDock");
        var tab = document.getElementById("visualDockTab");
        var rdDark = document.getElementById("visualThemeDark");
        var rdLight = document.getElementById("visualThemeLight");
        var selFs = document.getElementById("visualFontSize");
        var chkBold = document.getElementById("visualBold");
        if (!dock || !tab || !rdDark || !rdLight || !selFs || !chkBold) return;

        function readPrefs(){
          try { return JSON.parse(localStorage.getItem(KEY) || "{}"); } catch (e) { return {}; }
        }
        function savePrefs(p){
          try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) {}
        }
        function applyPrefs(p){
          var h = document.documentElement;
          if (p.theme === "light") h.classList.add("theme-light");
          else h.classList.remove("theme-light");
          var fs = p.fs === "lg" ? "1.12" : p.fs === "xl" ? "1.24" : "1";
          h.style.setProperty("--fs-scale", fs);
          if (p.bold) h.classList.add("a11y-bold");
          else h.classList.remove("a11y-bold");
        }
        function syncUIFromPrefs(p){
          if (p.theme === "light") rdLight.checked = true;
          else rdDark.checked = true;
          selFs.value = p.fs === "lg" || p.fs === "xl" ? p.fs : "normal";
          chkBold.checked = !!p.bold;
        }

        var prefs = readPrefs();
        applyPrefs(prefs);
        syncUIFromPrefs(prefs);

        function persistFromUI(){
          var p = {
            theme: rdLight.checked ? "light" : "dark",
            fs: selFs.value || "normal",
            bold: chkBold.checked
          };
          savePrefs(p);
          applyPrefs(p);
        }
        rdDark.addEventListener("change", persistFromUI);
        rdLight.addEventListener("change", persistFromUI);
        selFs.addEventListener("change", persistFromUI);
        chkBold.addEventListener("change", persistFromUI);

        tab.addEventListener("click", function(e){
          e.stopPropagation();
          var open = dock.classList.toggle("is-open");
          tab.setAttribute("aria-expanded", open ? "true" : "false");
        });
        document.addEventListener("click", function(e){
          if (!dock.contains(e.target)){
            dock.classList.remove("is-open");
            tab.setAttribute("aria-expanded", "false");
          }
        });

        dock.addEventListener("keydown", function(e){
          if (e.key === "Escape"){
            dock.classList.remove("is-open");
            tab.setAttribute("aria-expanded", "false");
          }
        });
      }

      function initIdDock(){
        if (!idDock || !idDockTab) return;
        closeIdCropper();
        idDockTab.addEventListener("click", function(e){
          e.stopPropagation();
          var open = idDock.classList.toggle("is-open");
          idDockTab.setAttribute("aria-expanded", open ? "true" : "false");
        });
        document.addEventListener("click", function(e){
          if (!idDock.contains(e.target)){
            idDock.classList.remove("is-open");
            idDockTab.setAttribute("aria-expanded", "false");
          }
        });
        idDock.addEventListener("keydown", function(e){
          if (e.key === "Escape"){
            idDock.classList.remove("is-open");
            idDockTab.setAttribute("aria-expanded", "false");
            closeIdCropper();
          }
        });
      }

      initVisualPreferences();
      initIdDock();
      init();
    })();
  </script>
</body>
</html>
