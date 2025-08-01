@echo off
chcp 65001 >nul
echo ========================================
echo     GRIDPOS PRINT SERVICE - SIMPLE
echo       INSTALADOR AUTOMATICO
echo ========================================
echo.

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: Ejecuta como administrador
    pause
    exit /b 1
)

echo 🔧 CONFIGURACIÓN
echo ================
echo.

:ask_api
echo 🌐 ¿Qué API usar?
echo    1) PRODUCCIÓN (api.gridpos.co)
echo    2) DEMO (api-demo.gridpos.co)
set /p API_CHOICE="Selecciona 1 o 2: "

if "%API_CHOICE%"=="1" (
    set API_TYPE=api
    set API_NAME=PRODUCCIÓN
) else if "%API_CHOICE%"=="2" (
    set API_TYPE=api-demo
    set API_NAME=DEMO
) else (
    echo ❌ Opción inválida
    goto ask_api
)

:ask_client
echo.
echo 🏢 Ingresa tu CLIENT SLUG:
set /p CLIENT_SLUG="Client Slug: "

if "%CLIENT_SLUG%"=="" (
    echo ❌ Client Slug obligatorio
    goto ask_client
)

echo.
echo 📋 CONFIGURACIÓN:
echo    🌐 API: %API_NAME%
echo    🏢 Client: %CLIENT_SLUG%
echo.
echo ¿Confirmar? (S/N)
set /p CONFIRM="Respuesta: "

if /i not "%CONFIRM%"=="S" (
    echo ❌ Cancelado
    pause
    exit /b 1
)

echo.
echo 🚀 INSTALANDO...

set INSTALL_DIR=C:\GridPos\PrintService
mkdir "%INSTALL_DIR%" 2>nul

copy "GridPosPrintService.exe" "%INSTALL_DIR%\" >nul

reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "ApiType" /t REG_SZ /d "%API_TYPE%" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "ClientSlug" /t REG_SZ /d "%CLIENT_SLUG%" /f >nul

echo.
echo ✅ INSTALACIÓN COMPLETADA
echo.
echo 📍 Ubicación: %INSTALL_DIR%
echo 🚀 Para ejecutar: %INSTALL_DIR%\GridPosPrintService.exe
echo.
echo 💡 TIP: Agrega al inicio de Windows para que ejecute automáticamente
echo.
pause
