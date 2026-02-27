<?php
/**
 * Formulario público de Solicitud de Financiamiento (Wizard).
 * Acceso sin login. Al enviar se crea una solicitud en el sistema.
 */
// Sin verificación de sesión: página pública
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Solicitud de Financiamiento - Solicitud de Crédito</title>

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
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
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
      font-size: 18px;
      letter-spacing:.2px;
    }

    .subtitle{
      margin:0;
      color: var(--muted);
      font-size: 13px;
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
      font-size: 14px;
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
      font-size: 12px;
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
      font-size: 16px;
    }
    .sectionTitle p{
      margin: 4px 0 0 0;
      font-size: 13px;
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
      font-size: 12px;
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
      outline: none;
    }
    input:focus, select:focus, textarea:focus{
      border-color: rgba(78,161,255,.55);
      box-shadow: 0 0 0 3px rgba(78,161,255,.15);
    }

    textarea{ min-height: 92px; resize: vertical; }

    .hint{
      margin-top: 6px;
      font-size: 12px;
      color: var(--muted);
    }

    .error{
      margin-top: 6px;
      font-size: 12px;
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
      font-size: 13px;
      display:none;
      max-width: calc(100% - 24px);
    }
    .toast.show{ display:block; }
    .toast.ok{ border-color: rgba(52,211,153,.5); background: rgba(52,211,153,.15); }
    .toast.err{ border-color: rgba(255,93,93,.5); background: rgba(255,93,93,.15); }
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
            <h2>Generales del Cliente</h2>
            <p>Datos del vehículo y del trámite (sucursal, auto, precio, abono, etc.).</p>
          </div>
        </div>
        <div class="grid">
          <div class="col-6">
            <label for="sucursal">Sucursal *</label>
            <input id="sucursal" name="sucursal" required maxlength="80" autocomplete="organization" />
            <div class="error" data-error-for="sucursal"></div>
          </div>
          <div class="col-6">
            <label for="nombre_gestor">Nombre del gestor *</label>
            <input id="nombre_gestor" name="nombre_gestor" required maxlength="80" />
            <div class="error" data-error-for="nombre_gestor"></div>
          </div>
          <div class="col-6">
            <label for="marca_auto">Marca del auto *</label>
            <input id="marca_auto" name="marca_auto" required maxlength="60" />
            <div class="error" data-error-for="marca_auto"></div>
          </div>
          <div class="col-6">
            <label for="modelo_auto">Modelo del auto *</label>
            <input id="modelo_auto" name="modelo_auto" required maxlength="60" />
            <div class="error" data-error-for="modelo_auto"></div>
          </div>
          <div class="col-4">
            <label for="anio_auto">Año *</label>
            <input id="anio_auto" name="anio_auto" inputmode="numeric" required pattern="^(19|20)\d{2}$" placeholder="YYYY" />
            <div class="hint">Ej: 2021</div>
            <div class="error" data-error-for="anio_auto"></div>
          </div>
          <div class="col-4">
            <label for="kms_cod_auto">KMS / Cód. auto *</label>
            <input id="kms_cod_auto" name="kms_cod_auto" required maxlength="40" />
            <div class="error" data-error-for="kms_cod_auto"></div>
          </div>
          <div class="col-4">
            <label for="precio_venta">Precio de venta (USD) *</label>
            <input id="precio_venta" name="precio_venta" inputmode="decimal" required placeholder="0.00" />
            <div class="error" data-error-for="precio_venta"></div>
          </div>
          <div class="col-4">
            <label for="abono">Abono (USD)</label>
            <input id="abono" name="abono" inputmode="decimal" placeholder="0.00" />
            <div class="error" data-error-for="abono"></div>
          </div>
        </div>
        <div class="rowActions">
          <div class="chip"><strong>Paso:</strong> 1 / 6</div>
          <div class="navBtns">
            <button type="button" class="primary" data-next>Siguiente</button>
          </div>
        </div>
      </fieldset>

      <fieldset data-step="1">
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
            <input id="cliente_nacimiento" name="cliente_nacimiento" type="date" required />
            <div class="error" data-error-for="cliente_nacimiento"></div>
          </div>
          <div class="col-3">
            <label for="cliente_edad">Edad *</label>
            <input id="cliente_edad" name="cliente_edad" inputmode="numeric" required pattern="^\d{1,3}$" placeholder="Ej: 35" />
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
            <label for="cliente_peso">Peso</label>
            <input id="cliente_peso" name="cliente_peso" inputmode="decimal" placeholder="kg" />
            <div class="error" data-error-for="cliente_peso"></div>
          </div>
          <div class="col-3">
            <label for="cliente_estatura">Estatura</label>
            <input id="cliente_estatura" name="cliente_estatura" inputmode="decimal" placeholder="cm" />
            <div class="error" data-error-for="cliente_estatura"></div>
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
          <div class="col-6">
            <label for="prov_dist_corr">Provincia, Distrito, Corregimiento *</label>
            <input id="prov_dist_corr" name="prov_dist_corr" required maxlength="120" />
            <div class="error" data-error-for="prov_dist_corr"></div>
          </div>
          <div class="col-6">
            <label for="tel_residencia">Teléfono de residencia</label>
            <input id="tel_residencia" name="tel_residencia" inputmode="tel" placeholder="Ej: 2XXX-XXXX" />
            <div class="error" data-error-for="tel_residencia"></div>
          </div>
          <div class="col-6">
            <label for="barriada_calle_casa">Barriada, No. calle, Casa No. *</label>
            <input id="barriada_calle_casa" name="barriada_calle_casa" required maxlength="140" />
            <div class="error" data-error-for="barriada_calle_casa"></div>
          </div>
          <div class="col-6">
            <label for="celular_cliente">Celular *</label>
            <input id="celular_cliente" name="celular_cliente" required inputmode="tel" placeholder="Ej: 6XXX-XXXX" />
            <div class="error" data-error-for="celular_cliente"></div>
          </div>
          <div class="col-6">
            <label for="edificio_apto">Edificio, Apartamento No.</label>
            <input id="edificio_apto" name="edificio_apto" maxlength="120" />
            <div class="error" data-error-for="edificio_apto"></div>
          </div>
          <div class="col-6">
            <label for="correo_residencial">Correo electrónico</label>
            <input id="correo_residencial" name="correo_residencial" type="email" placeholder="Opcional" />
            <div class="error" data-error-for="correo_residencial"></div>
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
            <input id="con_nacimiento" name="con_nacimiento" type="date" />
            <div class="error" data-error-for="con_nacimiento"></div>
          </div>
          <div class="col-3">
            <label for="con_edad">Edad</label>
            <input id="con_edad" name="con_edad" inputmode="numeric" pattern="^\d{1,3}$" />
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
            <label for="refp1_dir_lab">Personal 1 - Dirección laboral</label>
            <input id="refp1_dir_lab" name="refp1_dir_lab" maxlength="140" />
            <div class="error" data-error-for="refp1_dir_lab"></div>
          </div>
          <div class="col-6">
            <label for="refp2_nombre">Personal 2 - Nombre completo *</label>
            <input id="refp2_nombre" name="refp2_nombre" required maxlength="90" />
            <div class="error" data-error-for="refp2_nombre"></div>
          </div>
          <div class="col-6">
            <label for="refp2_cel">Personal 2 - Celular *</label>
            <input id="refp2_cel" name="refp2_cel" required inputmode="tel" />
            <div class="error" data-error-for="refp2_cel"></div>
          </div>
          <div class="col-6">
            <label for="refp2_dir_res">Personal 2 - Dirección residencial</label>
            <input id="refp2_dir_res" name="refp2_dir_res" maxlength="140" />
            <div class="error" data-error-for="refp2_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="refp2_dir_lab">Personal 2 - Dirección laboral</label>
            <input id="refp2_dir_lab" name="refp2_dir_lab" maxlength="140" />
            <div class="error" data-error-for="refp2_dir_lab"></div>
          </div>
          <div class="col-12" style="margin-top:6px"><div class="chip done"><strong>Familiares (2)</strong></div></div>
          <div class="col-6">
            <label for="reff1_nombre">Familiar 1 - Nombre completo *</label>
            <input id="reff1_nombre" name="reff1_nombre" required maxlength="90" />
            <div class="error" data-error-for="reff1_nombre"></div>
          </div>
          <div class="col-6">
            <label for="reff1_cel">Familiar 1 - Celular *</label>
            <input id="reff1_cel" name="reff1_cel" required inputmode="tel" />
            <div class="error" data-error-for="reff1_cel"></div>
          </div>
          <div class="col-6">
            <label for="reff1_dir_res">Familiar 1 - Dirección residencial</label>
            <input id="reff1_dir_res" name="reff1_dir_res" maxlength="140" />
            <div class="error" data-error-for="reff1_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="reff1_dir_lab">Familiar 1 - Dirección laboral</label>
            <input id="reff1_dir_lab" name="reff1_dir_lab" maxlength="140" />
            <div class="error" data-error-for="reff1_dir_lab"></div>
          </div>
          <div class="col-6">
            <label for="reff2_nombre">Familiar 2 - Nombre completo *</label>
            <input id="reff2_nombre" name="reff2_nombre" required maxlength="90" />
            <div class="error" data-error-for="reff2_nombre"></div>
          </div>
          <div class="col-6">
            <label for="reff2_cel">Familiar 2 - Celular *</label>
            <input id="reff2_cel" name="reff2_cel" required inputmode="tel" />
            <div class="error" data-error-for="reff2_cel"></div>
          </div>
          <div class="col-6">
            <label for="reff2_dir_res">Familiar 2 - Dirección residencial</label>
            <input id="reff2_dir_res" name="reff2_dir_res" maxlength="140" />
            <div class="error" data-error-for="reff2_dir_res"></div>
          </div>
          <div class="col-6">
            <label for="reff2_dir_lab">Familiar 2 - Dirección laboral</label>
            <input id="reff2_dir_lab" name="reff2_dir_lab" maxlength="140" />
            <div class="error" data-error-for="reff2_dir_lab"></div>
          </div>
          <div class="col-12" style="margin-top:10px">
            <label class="chip" style="display:flex;gap:10px;align-items:center;justify-content:flex-start">
              <input type="checkbox" id="acepta" name="acepta" required />
              Confirmo que la información es correcta y autorizo el uso para análisis de crédito *
            </label>
            <div class="error" data-error-for="acepta"></div>
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

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script>
    (function(){
      const STORAGE_KEY = "baes_financiamiento_wizard_v1";
      const API_URL = "api/solicitud_publica.php";

      const form = document.getElementById("wizardForm");
      const fieldsets = Array.from(form.querySelectorAll("fieldset"));
      const stepChips = document.getElementById("stepChips");
      const progressBar = document.getElementById("progressBar");
      const saveState = document.getElementById("saveState").querySelector("span");
      const toast = document.getElementById("toast");
      const btnSaveExit = document.getElementById("btnSaveExit");
      const btnClear = document.getElementById("btnClear");
      const btnSubmit = document.getElementById("btnSubmit");

      const stepLabels = ["Generales", "A. Cliente", "B. Dirección", "C. Laboral", "D. Cónyuge", "E. Referencias"];
      let step = 0;
      let toastTimer = null;

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
      }

      function readFormToObject(){
        var data = {};
        form.querySelectorAll("input, select, textarea").forEach(function(el){
          if(!el.name) return;
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
          if(v !== undefined && v !== null) el.value = v;
        });
        if(data.__meta && typeof data.__meta.step === "number"){
          step = Math.max(0, Math.min(fieldsets.length - 1, data.__meta.step));
        }
      }

      function saveDraft(mode){
        var payload = readFormToObject();
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
          saveState.textContent = mode === "manual" ? "Guardado" : "Auto-guardado";
        } catch(e) {}
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

        if(stepIndex === 0){
          ok = validateMoneyField("precio_venta", true) && ok;
          ok = validateMoneyField("abono", false) && ok;
          var precio = Number(form.elements["precio_venta"].value || 0);
          var abono = Number(form.elements["abono"].value || 0);
          if(!Number.isNaN(precio) && !Number.isNaN(abono) && abono > precio){
            ok = false;
            setError("abono", "El abono no puede ser mayor al precio de venta.");
          }
        }
        if(stepIndex === 2){
          var vivienda = form.elements["vivienda"] && form.elements["vivienda"].value;
          var monto = form.elements["vivienda_monto"] && form.elements["vivienda_monto"].value;
          if((vivienda === "Alquilada" || vivienda === "Hipotecada") && !monto){
            ok = false;
            setError("vivienda_monto", "Indique el monto (alquiler o hipoteca).");
          } else {
            ok = validateMoneyField("vivienda_monto", false) && ok;
          }
        }
        if(stepIndex === 3) ok = validateMoneyField("empresa_salario", true) && ok;
        if(stepIndex === 4 && tieneConyuge) ok = validateMoneyField("con_salario", false) && ok;
        if(stepIndex === 5){
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
      form.addEventListener("change", function(e){ if(e.target && e.target.name) saveDraft("auto"); });
      window.addEventListener("beforeunload", function(){ try{ saveDraft("auto"); }catch(e){} });

      function init(){
        makeChips();
        attachNavButtons();

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

          btnSubmit.disabled = true;
          btnSubmit.textContent = "Enviando…";

          fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
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

      init();
    })();
  </script>
</body>
</html>
