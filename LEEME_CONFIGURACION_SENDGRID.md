# ⚠️ IMPORTANTE: Configuración de SendGrid

## Problema de Seguridad Resuelto

GitHub detectó la API key de SendGrid en los archivos y bloqueó el push por seguridad.

## ✅ Solución Implementada

1. **API Key removida de todos los archivos del repositorio**
2. **Sistema configurado para usar archivo local** (`config/email.local.php`)
3. **Archivo local agregado a `.gitignore`** (no se subirá a Git)

## 📋 Pasos para Configurar en el Servidor

### Opción 1: Usar el Script (Recomendado)

1. **Copiar el script al servidor** (NO subirlo a Git):
   ```bash
   # En tu máquina local, copiar el archivo manualmente al servidor
   scp configurar_sendgrid_servidor.sh usuario@servidor:/home/ubuntu/motus/baes/
   ```

2. **En el servidor, ejecutar:**
   ```bash
   cd /home/ubuntu/motus/baes
   chmod +x configurar_sendgrid_servidor.sh
   ./configurar_sendgrid_servidor.sh
   ```

### Opción 2: Configuración Manual

1. **En el servidor, crear el archivo:**
   ```bash
   cd /home/ubuntu/motus/baes
   cp config/email.local.php.example config/email.local.php
   ```

2. **Editar `config/email.local.php` y agregar tu API key:**
   ```php
   'sendgrid_api_key' => 'TU_API_KEY_DE_SENDGRID_AQUI',
   ```

### Opción 3: Variables de Entorno

```bash
export SENDGRID_API_KEY="TU_API_KEY_DE_SENDGRID_AQUI"
```

## 🔒 Seguridad

- ✅ `config/email.local.php` está en `.gitignore`
- ✅ `configurar_sendgrid_servidor.sh` contiene la API key pero NO debe subirse a Git
- ✅ `env.example` solo tiene placeholders
- ✅ `config/email.php` no tiene la API key hardcodeada

## 📝 Archivos que NO deben subirse a Git

- `config/email.local.php` ✅ (ya en .gitignore)
- `configurar_sendgrid_servidor.sh` ⚠️ (debe agregarse a .gitignore)

## 🚀 Después de Configurar

```bash
# Instalar dependencias
composer install

# Probar el sistema
php test_email.php
```

