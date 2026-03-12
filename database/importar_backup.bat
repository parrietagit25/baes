@echo off
chcp 65001 >nul
echo Importando motus_baes_backup.sql en el contenedor MySQL...
echo.

if exist "%~dp0motus_baes_tabla_por_tabla.sql" (
    set "ARCHIVO=motus_baes_tabla_por_tabla.sql"
) else if exist "%~dp0motus_baes_backup.sql" (
    set "ARCHIVO=motus_baes_backup.sql"
) else (
    echo ERROR: No se encuentra ningun archivo SQL en la carpeta database.
    echo Usa: motus_baes_tabla_por_tabla.sql  o  copia motus_baes_backup.sql aqui.
    pause
    exit /b 1
)

echo Usando: %ARCHIVO%
docker exec -i motus_db mysql -u root -pmotus_root_2024 -e "source /docker-entrypoint-initdb.d/%ARCHIVO%"

if %errorlevel% equ 0 (
    echo.
    echo Importacion completada. Revisa la base motus_baes en phpMyAdmin.
) else (
    echo.
    echo Error en la importacion. Comprueba que el contenedor motus_db este corriendo.
)

pause
