<?php
declare(strict_types=1);

/**
 * Espejo de pruebas del formulario publico.
 *
 * Importante:
 * - No modifica el formulario original.
 * - Renderiza exactamente `solicitud_financiamiento.php`.
 * - Inyecta una capa de autollenado para pruebas manuales.
 */

ob_start();
require __DIR__ . '/solicitud_financiamiento.php';
$html = (string)ob_get_clean();

$inject = <<<'HTML'
<style>
  #qaTestDock {
    position: fixed;
    right: 14px;
    bottom: 14px;
    width: min(420px, calc(100vw - 24px));
    background: rgba(2,6,23,.95);
    color: #e2e8f0;
    border: 1px solid rgba(148,163,184,.4);
    border-radius: 12px;
    z-index: 999999;
    box-shadow: 0 10px 24px rgba(0,0,0,.35);
    font-family: Arial, sans-serif;
  }
  #qaTestDock h3 {
    margin: 0;
    padding: 10px 12px;
    font-size: 14px;
    border-bottom: 1px solid rgba(148,163,184,.25);
    color: #93c5fd;
  }
  #qaTestDock .qa-body { padding: 10px 12px 12px; font-size: 12px; }
  #qaTestDock .qa-row { margin-bottom: 8px; }
  #qaTestDock label { display: block; margin-bottom: 4px; color: #cbd5e1; }
  #qaTestDock input, #qaTestDock button {
    width: 100%;
    border-radius: 8px;
    border: 1px solid rgba(148,163,184,.45);
    background: #0f172a;
    color: #e2e8f0;
    padding: 8px;
    font-size: 12px;
  }
  #qaTestDock button {
    cursor: pointer;
    font-weight: 700;
    background: #1d4ed8;
    border-color: #1d4ed8;
  }
  #qaTestDock .qa-hint { color: #94a3b8; margin-top: 6px; }
</style>
<div id="qaTestDock" aria-label="Panel test formulario">
  <h3>Panel QA (espejo)</h3>
  <div class="qa-body">
    <div class="qa-row">
      <label for="qaVendorEmail">Email vendedor (token)</label>
      <input id="qaVendorEmail" type="email" placeholder="vendedor@dominio.com" />
    </div>
    <div class="qa-row">
      <label for="qaClientEmail">Email cliente</label>
      <input id="qaClientEmail" type="email" placeholder="cliente@dominio.com" />
    </div>
    <div class="qa-row">
      <button type="button" id="qaAutofill">Autollenar formulario completo</button>
    </div>
    <div class="qa-hint">
      Usa el boton "Adjuntar archivos (opcional)" del formulario original para probar adjuntos.
    </div>
  </div>
</div>
<script>
(function () {
  "use strict";

  function encB64Url(text) {
    var b64 = btoa(unescape(encodeURIComponent(text)));
    return b64.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
  }

  function q(id) { return document.getElementById(id); }
  function setVal(id, value) {
    var el = q(id);
    if (!el) return;
    el.value = value;
    el.dispatchEvent(new Event("input", { bubbles: true }));
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }
  function setCheck(id, checked) {
    var el = q(id);
    if (!el) return;
    el.checked = !!checked;
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function mmddyyyyYearsAgo(years) {
    var d = new Date();
    d.setFullYear(d.getFullYear() - years);
    var mm = String(d.getMonth() + 1).padStart(2, "0");
    var dd = String(d.getDate()).padStart(2, "0");
    var yy = d.getFullYear();
    return mm + "/" + dd + "/" + yy;
  }

  function runAutofill() {
    var ts = Date.now();
    var clientEmail = (q("qaClientEmail") && q("qaClientEmail").value.trim()) || ("qa.cliente+" + ts + "@example.com");
    var vendorEmail = q("qaVendorEmail") ? q("qaVendorEmail").value.trim() : "";

    var map = {
      cliente_nombre: "QA Cliente " + ts,
      cliente_estado_civil: "Soltero/a",
      cliente_sexo: "M",
      cliente_id: "8-TEST-" + String(ts).slice(-6),
      cliente_nacimiento: mmddyyyyYearsAgo(31),
      cliente_nacionalidad: "Panamena",
      cliente_dependientes: "1",
      cliente_correo: clientEmail,
      cliente_peso: "175",
      cliente_estatura: "1.75",
      vivienda: "Alquilada",
      vivienda_monto: "450.00",
      provincia: "Panama",
      distrito: "Panama",
      corregimiento: "Bella Vista",
      tel_residencia: "2233-4455",
      barriada: "El Cangrejo",
      celular_cliente: "6123-4567",
      casa_edif: "Apartamento",
      numero_casa_apto: "10B",
      direccion: "Calle 50, edificio de prueba",
      correo_residencial: clientEmail,
      empresa_nombre: "Empresa QA SA",
      empresa_ocupacion: "Analista QA",
      empresa_anios: "4",
      empresa_telefono: "3000-1111",
      empresa_salario: "1450.75",
      empresa_direccion: "Costa del Este, torre test",
      otros_ingresos: "Freelance 200 mensual",
      ocupacion_otros: "Consultoria",
      trabajo_anterior: "Empresa anterior de QA",
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
      anio_auto: "2021",
      kms_cod_auto: "28000",
      precio_venta: "16500.00",
      abono: "2500.00",
      sucursal: "Sucursal Test",
      nombre_gestor: "Gestor QA",
      comentarios_gestor: "Solicitud espejo de prueba QA."
    };

    Object.keys(map).forEach(function (key) { setVal(key, map[key]); });
    setCheck("tiene_conyuge", false);
    setCheck("acepta", true);

    // Firma minima valida para pasar validaciones del original.
    setVal("firmaData", "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAusB9WnR0mQAAAAASUVORK5CYII=");

    // Reemplaza token de URL original por token de test cuando se provee email vendedor.
    if (vendorEmail) {
      var token = encB64Url(vendorEmail);
      var url = new URL(window.location.href);
      url.searchParams.set("e", token);
      history.replaceState(null, "", url.toString());
    }

    alert("Formulario cargado con datos de prueba.\nAhora puedes adjuntar archivos y presionar Enviar.");
  }

  var btn = q("qaAutofill");
  if (btn) btn.addEventListener("click", runAutofill);
})();
</script>
HTML;

if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $inject . "\n</body>", $html);
} else {
    $html .= $inject;
}

echo $html;

