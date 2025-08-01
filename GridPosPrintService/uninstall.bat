@echo off
echo ========================================
echo   DESINSTALADOR GRIDPOS PRINT SERVICE
echo ========================================
echo.

REM Verificar permisos de administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Este script requiere permisos de administrador
    echo Ejecuta como administrador y intenta de nuevo.
    pause
    exit /b 1
)

echo ✓ Permisos de administrador verificados
echo.

REM Detener servicio
echo 🛑 Deteniendo servicio...
sc stop "GridPosPrintService" >nul 2>&1
timeout /t 3 /nobreak >nul

REM Eliminar servicio
echo 🗑️ Eliminando servicio de Windows...
sc delete "GridPosPrintService" >nul 2>&1

if %errorLevel% equ 0 (
    echo ✅ Servicio eliminado correctamente
) else (
    echo ⚠️ El servicio no existía o ya fue eliminado
)

REM Eliminar archivos
set INSTALL_DIR=C:\Program Files\GridPos\PrintService
echo 📁 Eliminando archivos de: %INSTALL_DIR%
if exist "%INSTALL_DIR%" (
    rmdir /s /q "%INSTALL_DIR%" 2>nul
    echo ✅ Archivos eliminados
) else (
    echo ⚠️ Directorio de instalación no encontrado
)

REM Eliminar entradas del registro
echo 🔧 Limpiando registro de Windows...
reg delete "HKLM\SOFTWARE\GridPos\PrintService" /f >nul 2>&1
if %errorLevel% equ 0 (
    echo ✅ Entradas del registro eliminadas
) else (
    echo ⚠️ Entradas del registro no encontradas
)

echo.
echo ========================================
echo     DESINSTALACIÓN COMPLETADA
echo ========================================
echo.
echo ✅ GridPos Print Service desinstalado completamente
echo ✅ Todos los archivos y configuraciones eliminados
echo.
echo El sistema está limpio y listo para una nueva instalación
echo si es necesario.
echo.
pause
