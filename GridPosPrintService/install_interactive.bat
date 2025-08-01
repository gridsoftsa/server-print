@echo off
chcp 65001 >nul
echo ========================================
echo    INSTALADOR GRIDPOS PRINT SERVICE
echo ========================================
echo.

REM Verificar permisos de administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: Este script requiere permisos de administrador
    echo Ejecuta como administrador y intenta de nuevo.
    pause
    exit /b 1
)

echo ✅ Permisos de administrador verificados
echo.

echo 🔧 CONFIGURACIÓN INICIAL
echo ========================
echo.

REM Solicitar tipo de API
:ask_api_type
echo 🌐 ¿Qué ambiente de API deseas usar?
echo    1) PRODUCCIÓN (api.gridpos.co)
echo    2) DEMO (api-demo.gridpos.co)
echo.
set /p API_CHOICE="Selecciona 1 o 2: "

if "%API_CHOICE%"=="1" (
    set API_TYPE=api
    set API_NAME=PRODUCCIÓN
) else if "%API_CHOICE%"=="2" (
    set API_TYPE=api-demo
    set API_NAME=DEMO
) else (
    echo ❌ Opción inválida. Selecciona 1 o 2.
    echo.
    goto ask_api_type
)

echo ✅ API seleccionada: %API_NAME% (https://%API_TYPE%.gridpos.co)
echo.

REM Solicitar Client Slug
:ask_client_slug
echo 🏢 Ingresa el CLIENT SLUG de tu empresa:
echo    (Ejemplo: mi-empresa, restaurante-xyz, etc.)
echo.
set /p CLIENT_SLUG="Client Slug: "

if "%CLIENT_SLUG%"=="" (
    echo ❌ El Client Slug es obligatorio.
    echo.
    goto ask_client_slug
)

echo ✅ Client Slug configurado: %CLIENT_SLUG%
echo.

REM Confirmar configuración
echo 📋 RESUMEN DE CONFIGURACIÓN
echo ===========================
echo 🌐 API: %API_NAME% (https://%API_TYPE%.gridpos.co)
echo 🏢 Client Slug: %CLIENT_SLUG%
echo 🔑 Authorization: f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3
echo.
echo ¿Es correcta esta configuración? (S/N)
set /p CONFIRM="Confirmar: "

if /i not "%CONFIRM%"=="S" (
    echo.
    echo 🔄 Reiniciando configuración...
    echo.
    goto ask_api_type
)

echo.
echo 🚀 INICIANDO INSTALACIÓN...
echo ============================

REM Detener servicio si ya existe
echo 🛑 Deteniendo servicio existente...
sc stop "GridPosPrintService" >nul 2>&1
sc delete "GridPosPrintService" >nul 2>&1

REM Crear directorio de instalación
set INSTALL_DIR=C:\Program Files\GridPos\PrintService
echo 📁 Creando directorio: %INSTALL_DIR%
mkdir "%INSTALL_DIR%" 2>nul

REM Copiar archivos
echo 📦 Copiando archivos del servicio...
copy "GridPosPrintService.exe" "%INSTALL_DIR%\" >nul
if %errorLevel% neq 0 (
    echo ❌ Error copiando GridPosPrintService.exe
    echo Verifica que el archivo existe en la carpeta actual.
    pause
    exit /b 1
)

copy "*.dll" "%INSTALL_DIR%\" >nul 2>&1
copy "appsettings.json" "%INSTALL_DIR%\" >nul 2>&1

REM Crear configuración en el registro
echo 🔧 Configurando registro de Windows...
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "ApiType" /t REG_SZ /d "%API_TYPE%" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "ClientSlug" /t REG_SZ /d "%CLIENT_SLUG%" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "AuthToken" /t REG_SZ /d "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "InstallPath" /t REG_SZ /d "%INSTALL_DIR%" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "Version" /t REG_SZ /d "1.0.0" /f >nul
reg add "HKLM\SOFTWARE\GridPos\PrintService" /v "InstallDate" /t REG_SZ /d "%DATE% %TIME%" /f >nul

REM Instalar como servicio de Windows
echo 🔧 Instalando servicio de Windows...
sc create "GridPosPrintService" binPath= "\"%INSTALL_DIR%\GridPosPrintService.exe\"" DisplayName= "GridPos Print Service" start= auto >nul

if %errorLevel% equ 0 (
    echo ✅ Servicio instalado correctamente

    REM Configurar descripción del servicio
    sc description "GridPosPrintService" "Servicio nativo de impresión para GridPos - Conectado a %API_NAME%" >nul

    REM Configurar recovery del servicio
    sc failure "GridPosPrintService" reset= 86400 actions= restart/60000/restart/60000/restart/60000 >nul

    REM Iniciar servicio
    echo 🚀 Iniciando servicio...
    sc start "GridPosPrintService" >nul

    if %errorLevel% equ 0 (
        echo ✅ Servicio iniciado correctamente
        echo.
        echo ========================================
        echo     ✅ INSTALACIÓN COMPLETADA
        echo ========================================
        echo.
        echo 🎯 GridPos Print Service configurado:
        echo    🌐 API: %API_NAME%
        echo    🏢 Client: %CLIENT_SLUG%
        echo    📍 Ubicación: %INSTALL_DIR%
        echo.
        echo 🔧 Configuración guardada en:
        echo    HKLM\SOFTWARE\GridPos\PrintService
        echo.
        echo 📊 Para verificar el estado:
        echo    sc query GridPosPrintService
        echo.
        echo 📝 Para ver logs:
        echo    Event Viewer ^> Applications and Services Logs
        echo.
        echo 🚀 El servicio ya está funcionando y monitoreando
        echo    la cola de impresión cada 2 segundos.
        echo.

        REM Hacer una prueba de conectividad
        echo 🔍 Probando conectividad con la API...
        ping -n 1 %API_TYPE%.gridpos.co >nul 2>&1
        if %errorLevel% equ 0 (
            echo ✅ Conectividad con %API_TYPE%.gridpos.co: OK
        ) else (
            echo ⚠️ No se pudo conectar con %API_TYPE%.gridpos.co
            echo   Verifica tu conexión a internet.
        )

    ) else (
        echo ❌ Error iniciando el servicio
        echo.
        echo 🔍 Para diagnosticar el problema:
        echo    1. Verifica Event Viewer ^> Applications and Services Logs
        echo    2. Ejecuta: sc query GridPosPrintService
        echo    3. Verifica que el archivo exe no esté bloqueado
    )
) else (
    echo ❌ Error instalando el servicio
    echo Verifica los permisos y intenta de nuevo
)

echo.
echo 📞 ¿Necesitas ayuda?
echo    🌐 Soporte: https://gridpos.com/soporte
echo    📧 Email: soporte@gridpos.com
echo.
pause
