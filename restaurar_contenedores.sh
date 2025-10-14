#!/bin/bash

# Script para restaurar contenedores existentes que se bajaron
echo "🔄 Restaurando contenedores existentes..."

# Verificar qué contenedores están ejecutándose
echo "📋 Contenedores actualmente ejecutándose:"
docker ps

echo ""
echo "📋 Todos los contenedores (incluyendo detenidos):"
docker ps -a

echo ""
echo "🔍 Buscando directorios de proyectos existentes..."

# Buscar directorios de proyectos en /home/ubuntu/motus
if [ -d "/home/ubuntu/motus" ]; then
    echo "📁 Directorios encontrados en /home/ubuntu/motus:"
    ls -la /home/ubuntu/motus/
    
    echo ""
    echo "🔍 Buscando archivos docker-compose.yml en proyectos existentes:"
    find /home/ubuntu/motus -name "docker-compose.yml" -type f 2>/dev/null
fi

echo ""
echo "📋 Verificando servicios de Docker:"
sudo systemctl status docker --no-pager -l

echo ""
echo "🔍 Verificando puertos en uso:"
netstat -tlnp | grep -E "(8082|8083|8084|8085|8086|8087|8088|8089|3310|3311|3312)"

echo ""
echo "📝 Para restaurar contenedores existentes, necesitamos:"
echo "1. Identificar qué proyectos tenían contenedores ejecutándose"
echo "2. Navegar a cada directorio del proyecto"
echo "3. Ejecutar 'docker-compose up -d' en cada uno"
echo ""
echo "¿Quieres que busque y restaure automáticamente los contenedores existentes?"
