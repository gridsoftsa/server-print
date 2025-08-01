@echo off
chcp 65001 >nul
echo ========================================
echo   VERIFICADOR DE CONFIGURACIÓN
echo     GridPos Print Service
echo ========================================
echo.

REM Verificar si el servicio está instalado
sc query "GridPosPrintService" >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ GridPos Print Service NO está instalado
    echo.
    echo Para instalarlo, ejecuta: install_interactive.bat
    echo.
    pause
    exit /b 1
)

echo ✅ GridPos Print Service está instalado
echo.

REM Obtener estado del servicio
echo 📊 ESTADO DEL SERVICIO
echo ======================
for /f "tokens=3" %%i in ('sc query "GridPosPrintService" ^| findstr "STATE"') do set SERVICE_STATE=%%i

if "%SERVICE_STATE%"=="RUNNING" (
    echo ✅ Estado: EJECUTÁNDOSE
) else (
    echo ⚠️ Estado: %SERVICE_STATE%
)
echo.

REM Leer configuración del registro
echo 🔧 CONFIGURACIÓN ACTUAL
echo ======================

REM Leer ApiType
for /f "tokens=3*" %%i in ('reg query "HKLM\SOFTWARE\GridPos\PrintService" /v "ApiType" 2^>nul ^| findstr "ApiType"') do set API_TYPE=%%j
if "%API_TYPE%"=="" set API_TYPE=NO CONFIGURADO

REM Leer ClientSlug
for /f "tokens=3*" %%i in ('reg query "HKLM\SOFTWARE\GridPos\PrintService" /v "ClientSlug" 2^>nul ^| findstr "ClientSlug"') do set CLIENT_SLUG=%%j
if "%CLIENT_SLUG%"=="" set CLIENT_SLUG=NO CONFIGURADO

REM Leer InstallPath
for /f "tokens=3*" %%i in ('reg query "HKLM\SOFTWARE\GridPos\PrintService" /v "InstallPath" 2^>nul ^| findstr "InstallPath"') do set INSTALL_PATH=%%j
if "%INSTALL_PATH%"=="" set INSTALL_PATH=NO CONFIGURADO

REM Leer Version
for /f "tokens=3*" %%i in ('reg query "HKLM\SOFTWARE\GridPos\PrintService" /v "Version" 2^>nul ^| findstr "Version"') do set VERSION=%%j
if "%VERSION%"=="" set VERSION=NO CONFIGURADO

echo 🌐 API Type: %API_TYPE%
if "%API_TYPE%"=="api" (
    echo    URL: https://api.gridpos.co/print-queue
) else if "%API_TYPE%"=="api-demo" (
    echo    URL: https://api-demo.gridpos.co/print-queue
) else (
    echo    ❌ API Type no válido
)

echo 🏢 Client Slug: %CLIENT_SLUG%
echo 🔑 Authorization: f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3
echo 📍 Instalación: %INSTALL_PATH%
echo 📦 Versión: %VERSION%
echo.

REM Probar conectividad
echo 🔍 PRUEBA DE CONECTIVIDAD
echo =========================
if "%API_TYPE%"=="api" (
    set API_HOST=api.gridpos.co
) else if "%API_TYPE%"=="api-demo" (
    set API_HOST=api-demo.gridpos.co
) else (
    set API_HOST=
)

if not "%API_HOST%"=="" (
    echo Probando conexión con %API_HOST%...
    ping -n 1 %API_HOST% >nul 2>&1
    if %errorLevel% equ 0 (
        echo ✅ Conectividad: OK
    ) else (
        echo ❌ Conectividad: FALLO
        echo    Verifica tu conexión a internet
    )
) else (
    echo ❌ No se puede probar conectividad - API Type no configurado
)
echo.

REM Verificar archivos de instalación
echo 📁 ARCHIVOS DE INSTALACIÓN
echo ==========================
if exist "%INSTALL_PATH%\GridPosPrintService.exe" (
    echo ✅ GridPosPrintService.exe
) else (
    echo ❌ GridPosPrintService.exe NO ENCONTRADO
)

if exist "%INSTALL_PATH%\appsettings.json" (
    echo ✅ appsettings.json
) else (
    echo ⚠️ appsettings.json NO ENCONTRADO
)
echo.

REM Logs recientes
echo 📝 LOGS RECIENTES
echo =================
echo Para ver logs detallados:
echo   1. Abre Event Viewer (eventvwr.exe)
echo   2. Ve a Applications and Services Logs
echo   3. Busca "GridPosPrintService"
echo.

REM Comandos útiles
echo 🛠️ COMANDOS ÚTILES
echo ==================
echo Reiniciar servicio:
echo   sc stop GridPosPrintService
echo   sc start GridPosPrintService
echo.
echo Ver estado detallado:
echo   sc queryex GridPosPrintService
echo.
echo Desinstalar completamente:
echo   uninstall.bat
echo.

REM Resumen final
if "%SERVICE_STATE%"=="RUNNING" (
    if not "%CLIENT_SLUG%"=="NO CONFIGURADO" (
        if not "%API_TYPE%"=="NO CONFIGURADO" (
            echo ========================================
            echo   ✅ CONFIGURACIÓN CORRECTA
            echo ========================================
            echo.
            echo El servicio está funcionando correctamente y
            echo monitoreando la cola de impresión cada 2 segundos.
        ) else (
            echo ========================================
            echo   ⚠️ CONFIGURACIÓN INCOMPLETA
            echo ========================================
            echo.
            echo Falta configurar API Type. Ejecuta:
            echo   install_interactive.bat
        )
    ) else (
        echo ========================================
        echo   ⚠️ CONFIGURACIÓN INCOMPLETA
        echo ========================================
        echo.
        echo Falta configurar Client Slug. Ejecuta:
        echo   install_interactive.bat
    )
) else (
    echo ========================================
    echo   ❌ SERVICIO NO EJECUTÁNDOSE
    echo ========================================
    echo.
    echo Intenta reiniciar el servicio:
    echo   sc start GridPosPrintService
)

echo.
pause
