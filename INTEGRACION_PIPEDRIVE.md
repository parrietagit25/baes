# 🚀 Integración con Pipedrive - Sistema de Solicitud de Crédito

## 📋 Resumen de la Integración

La integración con Pipedrive está **completamente funcional** y lista para usar. El sistema detecta automáticamente si tu cuenta de Pipedrive tiene acceso a la API de leads o si requiere una suscripción de pago.

## ✅ Estado Actual

- **Conexión básica con Pipedrive**: ✅ Funcionando
- **API de leads**: ⚠️ Requiere suscripción de pago (Error 402)
- **Sistema de alternativas**: ✅ Implementado y funcional
- **Importación CSV**: ✅ Completamente funcional
- **Estadísticas**: ✅ Implementadas

## 🎯 Cómo Usar la Integración

### Opción 1: Importación CSV (Recomendada - Funciona Ahora)

1. **Accede al sistema** y ve a "Integración Pipedrive" en el menú
2. **Haz clic en "Importar CSV"** (botón amarillo)
3. **Descarga la plantilla** para ver el formato requerido
4. **Exporta tus leads desde Pipedrive**:
   - Ve a Pipedrive → Personas
   - Selecciona los leads que quieres importar
   - Exporta en formato CSV
5. **Sube el archivo CSV** al sistema
6. **Los leads se importarán automáticamente** como solicitudes de crédito

### Opción 2: Creación Manual

1. **Ve a "Solicitudes de Crédito"** en el menú
2. **Haz clic en "Nueva Solicitud"**
3. **Completa los datos** del lead manualmente

## 📊 Formato CSV Requerido

```csv
nombre_cliente,email,telefono,cedula,direccion,empresa
Juan Pérez,juan.perez@email.com,12345678,123456789,Calle 1 Ciudad,Empresa ABC
María García,maria.garcia@email.com,87654321,987654321,Avenida 2 Ciudad,Compañía XYZ
```

### Campos Obligatorios:
- `nombre_cliente` - Nombre completo del cliente
- `email` - Correo electrónico

### Campos Opcionales:
- `telefono` - Número de teléfono
- `cedula` - Número de cédula
- `direccion` - Dirección completa
- `empresa` - Nombre de la empresa

## 🔧 Para Habilitar la API de Pipedrive

Si quieres usar la sincronización automática:

1. **Contacta al administrador** de tu cuenta Pipedrive
2. **Solicita habilitar** el acceso a la API de personas/leads
3. **Verifica que tu plan** incluya acceso a la API
4. **Una vez habilitado**, la integración automática funcionará

## 📈 Estadísticas Disponibles

### Estadísticas de Pipedrive (cuando la API esté habilitada):
- Leads en Pipedrive
- Leads Importados
- Última Sincronización
- Tasa de Conversión

### Estadísticas de Importación CSV (siempre disponibles):
- Importados Hoy
- Total Importados
- Errores
- Última Importación

## 🛠️ Archivos de la Integración

- `api/pipedrive.php` - API para comunicación con Pipedrive
- `pipedrive.php` - Página principal de integración
- `importar_csv.php` - Página de importación CSV
- `descargar_plantilla.php` - Descarga de plantilla CSV
- `api/estadisticas_csv.php` - API de estadísticas CSV

## 🚀 Próximos Pasos

1. **Usa la importación CSV** para empezar a trabajar inmediatamente
2. **Contacta al administrador de Pipedrive** para habilitar la API
3. **Una vez habilitada la API**, la sincronización automática funcionará
4. **Monitorea las estadísticas** para ver el progreso

## 💡 Consejos de Uso

- **Exporta leads regularmente** desde Pipedrive para mantener actualizado el sistema
- **Usa la plantilla CSV** para asegurar el formato correcto
- **Revisa las estadísticas** para monitorear el progreso
- **Contacta soporte** si necesitas ayuda con la configuración

## 🔍 Solución de Problemas

### Error 402 (Payment Required)
- **Causa**: Tu cuenta de Pipedrive no tiene acceso a la API de leads
- **Solución**: Usa la importación CSV o contacta al administrador de Pipedrive

### Error de conexión
- **Causa**: Problemas de red o API key incorrecta
- **Solución**: Verifica la conexión a internet y la API key

### Error en importación CSV
- **Causa**: Formato de archivo incorrecto o datos faltantes
- **Solución**: Usa la plantilla proporcionada y verifica los datos obligatorios

## 📞 Soporte

Si necesitas ayuda con la integración:
1. Revisa este documento
2. Ejecuta `php test_integracion_completa.php` para diagnosticar
3. Contacta al administrador del sistema

---

**¡La integración está lista para usar! Comienza importando leads via CSV mientras se resuelve el tema de la suscripción de Pipedrive.** 🚀
