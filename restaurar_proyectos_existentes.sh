#!/bin/bash

echo "🚀 Restaurando proyectos existentes en el servidor..."

# Función para restaurar un proyecto
restaurar_proyecto() {
    local directorio=$1
    local nombre=$2
    
    if [ -d "$directorio" ]; then
        echo "📁 Restaurando proyecto: $nombre en $directorio"
        cd "$directorio"
        
        if [ -f "docker-compose.yml" ] || [ -f "docker-compose.yaml" ]; then
            echo "   ✅ Encontrado docker-compose.yml"
            docker-compose up -d
            echo "   🟢 Proyecto $nombre restaurado"
        else
            echo "   ❌ No se encontró docker-compose.yml en $directorio"
        fi
        cd - > /dev/null
    else
        echo "   ❌ Directorio $directorio no existe"
    fi
    echo ""
}

echo "🔍 Buscando proyectos existentes..."

# Buscar en el directorio motus
if [ -d "/home/ubuntu/motus" ]; then
    echo "📂 Explorando /home/ubuntu/motus..."
    ls -la /home/ubuntu/motus/
    
    echo ""
    echo "🔄 Restaurando proyectos conocidos..."
    
    # Restaurar proyectos basándose en los contenedores que viste
    restaurar_proyecto "/home/ubuntu/motus/subastaspcr" "Subastas PCR"
    restaurar_proyecto "/home/ubuntu/motus/apppcr" "App PCR" 
    restaurar_proyecto "/home/ubuntu/motus/procurement" "Procurement"
    
    # Buscar otros proyectos
    echo "🔍 Buscando otros proyectos con docker-compose..."
    for dir in /home/ubuntu/motus/*/; do
        if [ -d "$dir" ]; then
            project_name=$(basename "$dir")
            if [ -f "$dir/docker-compose.yml" ] || [ -f "$dir/docker-compose.yaml" ]; then
                echo "   📁 Proyecto encontrado: $project_name"
                restaurar_proyecto "$dir" "$project_name"
            fi
        fi
    done
else
    echo "❌ Directorio /home/ubuntu/motus no encontrado"
fi

echo "📊 Estado final de contenedores:"
docker ps

echo ""
echo "🌐 Verificando puertos:"
netstat -tlnp | grep -E "(8082|8083|8084|8085|8086|8087|8088|8089|3310|3311|3312)" | sort

echo ""
echo "✅ Restauración completada. Verifica que todos los proyectos estén funcionando."
