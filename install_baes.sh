#!/bin/bash

# Script de instalación automática para BAES en Amazon EC2
# Ejecutar como: sudo ./install_baes.sh

set -e  # Salir si hay algún error

echo "🚀 Iniciando instalación de BAES en Amazon EC2..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar que se ejecute como root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

# 1. Actualizar sistema
print_status "Actualizando sistema..."
apt update && apt upgrade -y

# 2. Instalar dependencias
print_status "Instalando dependencias..."
apt install -y curl wget git unzip nginx certbot python3-certbot-nginx

# 3. Instalar Docker si no está instalado
if ! command -v docker &> /dev/null; then
    print_status "Instalando Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    usermod -aG docker ubuntu
    rm get-docker.sh
    print_success "Docker instalado correctamente"
else
    print_success "Docker ya está instalado"
fi

# 4. Instalar Docker Compose si no está instalado
if ! command -v docker-compose &> /dev/null; then
    print_status "Instalando Docker Compose..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    print_success "Docker Compose instalado correctamente"
else
    print_success "Docker Compose ya está instalado"
fi

# 5. Crear directorio para la aplicación
print_status "Creando estructura de directorios..."
mkdir -p /home/ubuntu/motus/baes
cd /home/ubuntu/motus/baes

# 6. Configurar Nginx
print_status "Configurando Nginx..."
if [ -f "nginx-motus.grupopcr.com.pa" ]; then
    cp nginx-motus.grupopcr.com.pa /etc/nginx/sites-available/motus.grupopcr.com.pa
    ln -sf /etc/nginx/sites-available/motus.grupopcr.com.pa /etc/nginx/sites-enabled/
    
    # Verificar configuración de Nginx
    if nginx -t; then
        print_success "Configuración de Nginx válida"
        systemctl reload nginx
    else
        print_error "Error en configuración de Nginx"
        exit 1
    fi
else
    print_warning "Archivo de configuración de Nginx no encontrado"
fi

# 7. Configurar archivo .env
print_status "Configurando variables de entorno..."
if [ -f "env.example" ]; then
    cp env.example .env
    print_success "Archivo .env creado desde env.example"
    print_warning "Recuerda editar el archivo .env con tus configuraciones"
else
    print_warning "Archivo env.example no encontrado"
fi

# 8. Construir y ejecutar contenedores
print_status "Construyendo contenedores Docker..."
if [ -f "docker-compose.yml" ]; then
    docker-compose build
    print_success "Contenedores construidos correctamente"
    
    print_status "Iniciando contenedores..."
    docker-compose up -d
    print_success "Contenedores iniciados correctamente"
else
    print_error "Archivo docker-compose.yml no encontrado"
    exit 1
fi

# 9. Configurar SSL con Let's Encrypt
print_status "Configurando SSL con Let's Encrypt..."
if command -v certbot &> /dev/null; then
    print_warning "Para configurar SSL, ejecuta manualmente:"
    print_warning "sudo certbot --nginx -d motus.grupopcr.com.pa -d www.motus.grupopcr.com.pa"
else
    print_error "Certbot no está instalado"
fi

# 10. Configurar firewall (opcional)
print_status "Configurando firewall..."
if command -v ufw &> /dev/null; then
    ufw allow 22/tcp    # SSH
    ufw allow 80/tcp    # HTTP
    ufw allow 443/tcp   # HTTPS
    ufw --force enable
    print_success "Firewall configurado"
else
    print_warning "UFW no está instalado, configurando manualmente..."
fi

# 11. Verificar instalación
print_status "Verificando instalación..."
sleep 10  # Esperar a que los contenedores se inicien completamente

echo ""
echo "🔍 Verificación de contenedores:"
docker-compose ps

echo ""
echo "🔍 Verificación de puertos:"
netstat -tlnp | grep -E "(8086|3312|8089)"

echo ""
echo "🔍 Verificación de Nginx:"
systemctl status nginx --no-pager -l

# 12. Información final
echo ""
echo "🎉 ¡Instalación completada!"
echo ""
echo "📋 Información importante:"
echo "   • Aplicación: http://$(curl -s ifconfig.me):8086"
echo "   • phpMyAdmin: http://$(curl -s ifconfig.me):8089"
echo "   • Base de datos: localhost:3312"
echo ""
echo "🔧 Próximos pasos:"
echo "   1. Configurar SSL: sudo certbot --nginx -d motus.grupopcr.com.pa"
echo "   2. Editar archivo .env con tus configuraciones"
echo "   3. Ejecutar migraciones de base de datos si es necesario"
echo "   4. Probar la aplicación en https://motus.grupopcr.com.pa"
echo ""
echo "📝 Comandos útiles:"
echo "   • Ver logs: docker-compose logs -f"
echo "   • Reiniciar: docker-compose restart"
echo "   • Detener: docker-compose down"
echo "   • Iniciar: docker-compose up -d"
echo ""
print_success "¡BAES está listo para usar! 🚀"
