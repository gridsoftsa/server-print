@echo off
title GridPos Printer Service - Ultra Fast
color 0A

echo ========================================
echo    GridPos Printer Service - Ultra Fast
echo ========================================
echo.

REM Configuración
set "API_URL=https://api.gridpos.co/print-queue"
set "CLIENT_SLUG=tu-client-slug"
set "AUTH_TOKEN=f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3"
set "INTERVAL=200"

REM Verificar si curl está disponible
curl --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: curl no está instalado
    echo Instalando curl...
    powershell -Command "Invoke-WebRequest -Uri 'https://curl.se/windows/dl-8.4.0_5/curl-8.4.0_5-win64-mingw.zip' -OutFile 'curl.zip'"
    powershell -Command "Expand-Archive -Path 'curl.zip' -DestinationPath 'C:\curl' -Force"
    set "PATH=%PATH%;C:\curl\bin"
    del curl.zip
)

echo Configuración:
echo API URL: %API_URL%
echo Client Slug: %CLIENT_SLUG%
echo Intervalo: %INTERVAL%ms
echo.
echo Presiona Ctrl+C para detener
echo.

:loop
REM Verificar cola de impresión
curl -s -H "Authorization: %AUTH_TOKEN%" -H "X-Client-Slug: %CLIENT_SLUG%" "%API_URL%" > temp_response.json 2>nul

if %errorlevel% equ 0 (
    REM Verificar si hay contenido en la respuesta
    for %%A in (temp_response.json) do set size=%%~zA
    if !size! gtr 10 (
        echo [%time%] 📨 Trabajos de impresión encontrados
        echo [%time%] Procesando trabajos...

        REM Aquí procesarías los trabajos
        REM Por ahora solo simulamos
        echo [%time%] ✅ Trabajos procesados
    ) else (
        echo [%time%] ⏳ Sin trabajos pendientes
    )
) else (
    echo [%time%] ❌ Error conectando al servidor
)

REM Pausa ultra rápida
timeout /t 0 /nobreak >nul
goto loop
