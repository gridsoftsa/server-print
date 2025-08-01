@echo off
echo ========================================
echo    INSTALADOR GRIDPOS PRINT SERVICE
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

REM Detener servicio si ya existe
echo 🔄 Deteniendo servicio existente...
sc stop "GridPosPrintService" >nul 2>&1
sc delete "GridPosPrintService" >nul 2>&1

REM Crear directorio de instalación
set INSTALL_DIR=C:\Program Files\GridPos\PrintService
echo 📁 Creando directorio de instalación: %INSTALL_DIR%
mkdir "%INSTALL_DIR%" 2>nul

REM Copiar archivos
echo 📦 Copiando archivos del servicio...
copy "GridPosPrintService.exe" "%INSTALL_DIR%\" >nul
copy "*.dll" "%INSTALL_DIR%\" >nul 2>&1
copy "appsettings.json" "%INSTALL_DIR%\" >nul 2>&1

REM Crear entradas en el registro
echo 🔧 Configurando registro de Windows...
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "ApiBaseUrl" /t REG_SZ /d "http://localhost:8000" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "InstallPath" /t REG_SZ /d "%INSTALL_DIR%" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "Version" /t REG_SZ /d "1.0.0" /f >nul

REM Instalar como servicio de Windows
echo 🔧 Instalando servicio de Windows...
sc create "GridPosPrintService" binPath= "\"%INSTALL_DIR%\GridPosPrintService.exe\"" DisplayName= "GridPos Print Service" start= auto >nul

if %errorLevel% equ 0 (
    echo ✅ Servicio instalado correctamente

    REM Configurar descripción del servicio
    sc description "GridPosPrintService" "Servicio nativo de impresión para GridPos - Ultra optimizado para Windows 10/11" >nul

    REM Iniciar servicio
    echo 🚀 Iniciando servicio...
    sc start "GridPosPrintService" >nul

    if %errorLevel% equ 0 (
        echo ✅ Servicio iniciado correctamente
        echo.
        echo ========================================
        echo     INSTALACIÓN COMPLETADA
        echo ========================================
        echo.
        echo ✓ GridPos Print Service instalado
        echo ✓ Configurado para inicio automático
        echo ✓ Servicio activo y funcionando
        echo.
        echo 📍 Ubicación: %INSTALL_DIR%
        echo 🔧 Configuración: HKLM\SOFTWARE\GridPos\PrintService
        echo.
        echo Para verificar el estado:
        echo   sc query GridPosPrintService
        echo.
        echo Para ver logs:
        echo   Event Viewer ^> Applications and Services Logs
        echo.
    ) else (
        echo ❌ Error iniciando el servicio
        echo Verifica la configuración en el Event Viewer
    )
) else (
    echo ❌ Error instalando el servicio
    echo Verifica los permisos y intenta de nuevo
)

echo.
pause
