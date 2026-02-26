# Comandos Docker Útiles

## Acceder al Contenedor de Base de Datos

```bash
# Entrar al contenedor MySQL
docker exec -it motus_db bash

# O directamente a MySQL
docker exec -it motus_db mysql -u motus_user -p
# Contraseña: motus_pass_2024
```

## Acceder al Contenedor PHP

```bash
# Entrar al contenedor PHP
docker exec -it motus_php bash

# Ejecutar Composer dentro del contenedor
docker exec -it motus_php composer install

# Ejecutar comandos PHP
docker exec -it motus_php php test_email.php
```

## Acceder a phpMyAdmin

```bash
# Abrir en navegador
http://localhost:8089
# O desde el servidor
http://motus.grupopcr.com.pa:8089
```

## Comandos Útiles

```bash
# Ver logs del contenedor
docker logs motus_db
docker logs motus_php

# Reiniciar contenedores
docker restart motus_db
docker restart motus_php

# Ver estado
docker ps

# Detener contenedores
docker stop motus_db motus_php motus_phpmyadmin

# Iniciar contenedores
docker start motus_db motus_php motus_phpmyadmin
```

