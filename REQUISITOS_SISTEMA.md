# Requisitos del Sistema BAES - Motus
## Actualización del Sistema de Solicitudes de Crédito

---

## 📋 ÍNDICE

1. [Notificaciones y Alertas](#1-notificaciones-y-alertas)
2. [Integración Pipedrive](#2-integración-pipedrive)
3. [Solicitudes de Crédito](#3-solicitudes-de-crédito)
4. [Usuarios Banco](#4-usuarios-banco)
5. [Formularios y Campos](#5-formularios-y-campos)
6. [Proceso Post-Aprobación](#6-proceso-post-aprobación)
7. [Reservas de Autos](#7-reservas-de-autos)

---

## 1. NOTIFICACIONES Y ALERTAS

### 1.1 Notificación Email al Vendedor
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Cuando el banco responde a una solicitud, enviar email automático al vendedor asociado
- El email debe incluir:
  - Nombre del cliente
  - Respuesta del banco (Aprobado/Pre-aprobado/Rechazado)
  - Términos ofrecidos (letra, plazo, abono)
  - Link directo a la solicitud

**Campos necesarios:**
- Email del vendedor en la tabla `usuarios`
- Configuración SMTP para envío de correos

### 1.2 Alerta de Tiempo de Respuesta (24 horas)
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Mostrar alerta visual en solicitudes donde el banco asignado tiene más de 24 horas sin responder
- Indicador: Badge rojo/amarillo en la columna de estado
- Tooltip: "Sin respuesta desde: [fecha]"

**Query sugerido:**
```sql
SELECT * FROM solicitudes_credito 
WHERE estado = 'En Revisión Banco' 
AND TIMESTAMPDIFF(HOUR, fecha_actualizacion, NOW()) > 24
```

### 1.3 Botón de Recordatorio a Bancos
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Botón en cada solicitud: "Enviar Recordatorio"
- Envía email de recordatorio al usuario banco asignado
- Notifica al gestor que se envió el recordatorio
- Contador de recordatorios enviados

### 1.4 Notificación de Reenvío de Solicitudes
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Cuando se reasigna una solicitud a otro usuario banco, enviar notificación
- Email al nuevo usuario banco asignado
- Notificar al banco anterior (opcional)

---

## 2. INTEGRACIÓN PIPEDRIVE

### 2.1 Email de Pipedrive Manual
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Campo adicional en formulario de solicitud: "Email Pipedrive"
- Este email se usa para comunicación directa con el lead en Pipedrive
- Integración futura: Sincronizar comunicación bidireccional

### 2.2 Filtros en Leads de Pipedrive
**Prioridad:** ALTA  
**Estado:** Pendiente

**Filtros requeridos:**
1. **Forma de Pago:** Solo traer leads con "Financiamiento" o "Crédito"
   - Columna: `forma_de_pago` (o equivalente en API)
   - Valores permitidos: Financiamiento, Crédito
   - Omitir: Contado, otros

2. **Buscador (Siempre incluido):**
   - Teléfono principal (prioritario)
   - Celular
   - Nombre del cliente

3. **Filtro por Vendedor:**
   - Filtrar por vendedor asignado en Pipedrive

4. **Etapa del Deal:**
   - Estado actual del trato en Pipedrive

5. **Fecha de Creación:**
   - Rango de fechas cuando el cliente fue creado en Pipedrive

**Notas técnicas:**
- Verificar estructura de campos en Pipedrive API
- La forma de pago está en la "vitacora de gestión" según especificación

### 2.3 Adjuntos de Pipedrive
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Poder ver/descargar adjuntos que ya están en Pipedrive
- Sincronizar documentos adjuntos al importar lead
- Mostrar icono de adjunto si existen documentos en Pipedrive

---

## 3. SOLICITUDES DE CRÉDITO

### 3.1 Múltiples Usuarios Banco por Institución
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Poder asignar múltiples usuarios banco de la misma institución a una solicitud
- Gestión individual de respuestas por cada usuario banco
- Tab separada: "Respuestas Usuarios Banco" con lista de todos los bancos evaluando

**Cambio de terminología:**
- ❌ Cambiar "Banco" por "Financiación"
- ✅ Razón: No solo bancos financian, hay otras entidades financieras

### 3.2 Reasignación de Solicitudes
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Botón "Reasignar" en cada solicitud
- Modal para seleccionar nuevo usuario financiación
- Mantener historial de asignaciones anteriores
- Notificar al nuevo usuario asignado

### 3.3 Guardado de Cambios
**Prioridad:** CRÍTICA  
**Estado:** Bug Reportado

**Descripción:**
- ❌ Problema: No se están guardando los cambios en las solicitudes
- ✅ Solución: Revisar funciones JavaScript de guardado
- Verificar llamadas AJAX a `api/solicitudes.php`
- Logs de errores en consola del navegador

### 3.4 Botón de Reserva de Auto
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Botón "Reservar Auto" en el análisis del gestor
- Actualizar estado del auto como "Reservado"
- Registrar fecha de reserva
- Mostrar en columna dedicada en listado de solicitudes

**Tabla de reservas (crear):**
```sql
CREATE TABLE reservas_auto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    auto_info TEXT,
    fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE,
    estado ENUM('Activa', 'Vencida', 'Cancelada'),
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id)
);
```

### 3.5 Muro de Tiempo - Respuesta del Banco
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Mostrar en el muro cuando el banco responda
- Incluir términos de la aprobación
- Diferente color/icono para respuestas de banco vs comentarios normales

---

## 4. USUARIOS BANCO

### 4.1 Respuestas del Banco (Ampliado)
**Prioridad:** ALTA  
**Estado:** Pendiente

**Opciones de respuesta actuales:**
- Pendiente
- Aprobado
- Pre-aprobado (❌ tiene typo en spec: "preaprobado")
- Rechazado

**Nuevas opciones agregar:**
- ✅ **Aprobado Condicional**

**Campos de respuesta:**
- Valor del auto
- Abono
- Plazo (meses)
- Letra mensual
- Detalles de promoción
- Comentarios del ejecutivo

### 4.2 Adjuntos por Usuario Banco
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Cada usuario banco puede subir sus propios adjuntos
- Documentos del análisis bancario
- Archivos privados del banco (no visibles para gestor)
- O archivos compartidos (visibles para todos)

### 4.3 Evaluación de Múltiples Autos
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Poder evaluar más de un auto en la misma solicitud
- Tabla de vehículos a evaluar
- El banco debe responder para TODOS los autos cargados
- Cada auto con sus condiciones propias

**Estructura sugerida:**
```sql
ALTER TABLE solicitudes_credito ADD COLUMN vehiculos_evaluados JSON;

-- O mejor:
CREATE TABLE vehiculos_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    marca VARCHAR(100),
    modelo VARCHAR(100),
    año INT,
    precio DECIMAL(15,2),
    condiciones_banco JSON, -- respuesta individual del banco
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id)
);
```

### 4.4 Re-evaluación a Bancos
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Botón "Re-evaluar" en solicitudes existentes
- Enviar solicitud actualizada a los bancos ya asignados
- Notificar que hay cambios/modificaciones
- Mantener histórico de evaluaciones

---

## 5. FORMULARIOS Y CAMPOS

### 5.1 Validación de Hijos
**Prioridad:** BAJA  
**Estado:** Pendiente

**Descripción:**
- ❌ Problema: Permite valores negativos en campo "Hijos"
- ✅ Solución: Validación mínima = 0

### 5.2 Cambios en Perfil Financiero
**Prioridad:** ALTA  
**Estado:** Pendiente

**Cambios en campos:**

| Campo Anterior | Cambio | Nuevo Nombre | Tipo |
|----------------|--------|--------------|------|
| Ingreso | Cambiar | Salario Base | DECIMAL(15,2) |
| - | Agregar | Otros Ingresos | DECIMAL(15,2) |
| Tiempo laborar | Agregar campo relacionado | Continuidad Laboral | VARCHAR |
| - | Agregar | Fecha de Actividad | DATE |
| - | Agregar | Profesión | VARCHAR(100) |
| - | Agregar | Ocupación | VARCHAR(100) |
| Estabilidad laboral | Agregar campo adicional | Cantidad Años/Tiempo | VARCHAR(50) |

**Nota:** Profesión ≠ Ocupación
- Profesión: Titulación académica
- Ocupación: Cargo actual/trabajo que realiza

### 5.3 Auto Apartado (Visible para Banco)
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Campo: "Auto Apartado" (checkbox o sí/no)
- Este campo debe ser visible y destacado para el usuario banco
- Importante para la aprobación

### 5.4 Teléfono Principal
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Priorizar campo "Teléfono" como teléfono principal
- Este es el que siempre viene de Pipedrive
- Sincronizar con ID del cliente en Pipedrive

---

## 6. PROCESO POST-APROBACIÓN

### 6.1 Decisión del Cliente
**Prioridad:** ALTA  
**Estado:** Pendiente

**Flujo:**
1. Banco responde → Cliente recibe notificación
2. Cliente decide: Acepta / Rechaza
3. Si acepta → Siguientes pasos automáticos

### 6.2 Documentos Post-Aprobación
**Prioridad:** ALTA  
**Estado:** Pendiente

**Documentos requeridos:**

1. **Proforma**
   - Generada automáticamente por Automarket
   - Generada por Operaciones
   - Estado: Pendiente / Generada / Enviada

2. **Cita de Firma**
   - Banco y Automarket pautan cita
   - Campo: Fecha y hora de cita
   - Registro de asistencia: ✅ Asistió / ❌ No asistió
   - Notificaciones de recordatorio de cita

3. **Carta Promesa**
   - Fecha de liberación
   - Estado: Pendiente / Liberada

4. **Facturación (Último paso)**
   - Click final envía información a OCTO
   - Webhook o API a sistema OCTO
   - Estado: Facturado

### 6.3 Envío de Email al Cliente (Aprobación)
**Prioridad:** ALTA  
**Estado:** Pendiente

**Descripción:**
- Email automático cuando el banco aprueba
- Plantilla personalizable
- Incluir:
  - Felicitaciones
  - Términos de la aprobación
  - Próximos pasos
  - Documentos adjuntos (si aplica)
  - Contacto para consultas

---

## 7. RESERVAS DE AUTOS

### 7.1 Columna de Reservas en Solicitudes
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Descripción:**
- Agregar columna "Reserva" en tabla de solicitudes
- Indicador visual: ✅ Reservado / ⭕ Disponible
- Tooltip: Fecha de reserva

### 7.2 Gestión de Reservas
**Prioridad:** MEDIA  
**Estado:** Pendiente

**Funcionalidades:**
- Extender fecha de reserva
- Cancelar reserva
- Notificar vencimiento de reserva
- Conflicto si dos solicitudes reservan el mismo auto

---

## 8. OTROS REQUISITOS

### 8.1 Lista de Ejecutivos/Vendedores
**Prioridad:** BAJA  
**Estado:** Pedido

**Descripción:**
- Exportar o generar reporte de todos los ejecutivos/vendedores
- Con sus estadísticas de ventas

### 8.2 Campos Cambiados - Terminología
**Prioridad:** MEDIA  
**Estado:** Pendiente

| Actual | Nuevo |
|--------|-------|
| Banco | Financiación |
| Usuario Banco | Usuario Financiación |

---

## 📊 RESUMEN DE PRIORIDADES

### 🔴 CRÍTICO (Esta semana)
1. Arreglar guardado de solicitudes
2. Filtrar Pipedrive solo financiamientos
3. Alertas de 24 horas sin respuesta
4. Botón reservar auto

### 🟠 ALTO (Este mes)
1. Notificación email al vendedor
2. Múltiples usuarios banco
3. Reasignar solicitudes
4. Evaluar múltiples autos
5. Cambios en perfil financiero
6. Email plantilla al cliente

### 🟡 MEDIO (Próximos 2 meses)
1. Adjuntos usuario banco
2. Filtros avanzados en Pipe
3. Adjuntos de Pipedrive
4. Muro - respuesta banco
5. Botón re-evaluar

### 🟢 BAJO (Mejoras)
1. Validación hijos negativos
2. Lista ejecutivos
3. Terminología banco → financiación

---

## 📝 NOTAS TÉCNICAS

### Base de Datos
- Contraseña: `motus_pass_2024`
- Base de datos: `solicitud_credito`

### Reuniones Registradas
- 21/10/2025
- 22/10/2025
- 23/10/2025
- 24/10/2025

### Contacto
- Sistema: BAES (Sistema de Solicitudes de Crédito)
- Cliente: Motus
- Desarrollador: Pendiente asignación

---

**Última actualización:** 24/10/2025  
**Versión del documento:** 1.0
