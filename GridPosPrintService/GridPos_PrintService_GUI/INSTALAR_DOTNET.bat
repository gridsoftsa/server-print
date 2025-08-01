@echo off
chcp 65001 >nul
echo ========================================
echo    INSTALADOR AUTOMÁTICO .NET 6 SDK
echo      🚀 Para GridPos Print Service
echo ========================================
echo.

echo 🎯 INSTALADOR .NET 6 SDK PARA WINDOWS
echo =====================================

REM Verificar si ya está instalado
echo 🔍 Verificando si .NET 6 SDK ya está instalado...

if exist "C:\Program Files\dotnet\dotnet.exe" (
    echo ✅ .NET encontrado en Program Files
    "C:\Program Files\dotnet\dotnet.exe" --version 2>nul
    if %errorlevel% equ 0 (
        echo ✅ .NET SDK ya está instalado y funcionando
        echo.
        echo 🚀 Puedes proceder a ejecutar COMPILADOR_FINAL.bat
        pause
        exit /b 0
    )
)

echo ⚠️ .NET 6 SDK no está instalado o no funciona correctamente
echo.

echo 📥 DESCARGANDO E INSTALANDO .NET 6 SDK:
echo =======================================

REM Verificar arquitectura del sistema
if "%PROCESSOR_ARCHITECTURE%"=="AMD64" (
    set ARCH=x64
    set DOWNLOAD_URL=https://download.microsoft.com/download/7/b/6/7b654c6a-e8c4-4f62-b8b4-7b6b9d6b6c4a/dotnet-sdk-6.0.427-win-x64.exe
) else if "%PROCESSOR_ARCHITECTURE%"=="x86" (
    set ARCH=x86
    set DOWNLOAD_URL=https://download.microsoft.com/download/7/b/6/7b654c6a-e8c4-4f62-b8b4-7b6b9d6b6c4a/dotnet-sdk-6.0.427-win-x86.exe
) else (
    echo ❌ Arquitectura no soportada: %PROCESSOR_ARCHITECTURE%
    pause
    exit /b 1
)

echo 🎯 Arquitectura detectada: %ARCH%
echo 🌐 URL de descarga: %DOWNLOAD_URL%
echo.

REM Crear carpeta temporal
set TEMP_DIR=%TEMP%\GridPosDotNetInstaller
if not exist "%TEMP_DIR%" mkdir "%TEMP_DIR%"

echo 📥 Descargando .NET 6 SDK...
echo ⏳ Esto puede tomar varios minutos dependiendo de tu conexión...

REM Usar PowerShell para descargar (más confiable que bitsadmin)
powershell -Command "try { Invoke-WebRequest -Uri '%DOWNLOAD_URL%' -OutFile '%TEMP_DIR%\dotnet-sdk-installer.exe' -UseBasicParsing; Write-Host 'Descarga completada exitosamente' } catch { Write-Host 'Error en la descarga:' $_.Exception.Message; exit 1 }"

if %errorlevel% neq 0 (
    echo ❌ ERROR: Fallo en la descarga
    echo.
    echo 🔧 SOLUCIONES ALTERNATIVAS:
    echo 1. Verificar conexión a internet
    echo 2. Desactivar temporalmente antivirus/firewall
    echo 3. Descargar manualmente desde:
    echo    🌐 https://dotnet.microsoft.com/download/dotnet/6.0
    echo 4. Buscar "Download .NET 6.0 SDK" y seleccionar Windows %ARCH%
    echo.
    pause
    exit /b 1
)

echo ✅ Descarga completada: %TEMP_DIR%\dotnet-sdk-installer.exe
echo.

echo 🔧 INSTALANDO .NET 6 SDK...
echo ===========================
echo ⚠️ Se abrirá el instalador de Microsoft
echo 📋 PASOS A SEGUIR:
echo 1. Hacer clic en "Install" o "Instalar"
echo 2. Aceptar términos y condiciones
echo 3. Esperar a que termine la instalación
echo 4. Hacer clic en "Close" o "Cerrar"
echo.
echo 🚀 Presiona cualquier tecla para iniciar la instalación...
pause >nul

REM Ejecutar instalador con privilegios elevados
echo 🔧 Ejecutando instalador...
"%TEMP_DIR%\dotnet-sdk-installer.exe" /install /quiet /norestart

if %errorlevel% equ 0 (
    echo ✅ Instalación completada exitosamente
) else (
    echo ⚠️ El instalador se ejecutó pero reportó código: %errorlevel%
    echo 💡 Esto puede ser normal si la instalación fue exitosa
)

echo.
echo 🧹 Limpiando archivos temporales...
del "%TEMP_DIR%\dotnet-sdk-installer.exe" >nul 2>&1
rmdir "%TEMP_DIR%" >nul 2>&1

echo.
echo 🔍 VERIFICANDO INSTALACIÓN:
echo ===========================

REM Esperar un momento para que el sistema se actualice
timeout /t 3 /nobreak >nul

REM Actualizar PATH (para esta sesión)
call :RefreshPath

REM Verificar instalación
echo 🔧 Verificando .NET SDK...
dotnet --version >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ ¡.NET 6 SDK instalado exitosamente!
    echo 📊 Versión instalada:
    dotnet --version
    echo.
    echo 🎉 INSTALACIÓN COMPLETADA
    echo ========================
    echo ✅ .NET 6 SDK listo para usar
    echo 🚀 Ahora puedes ejecutar COMPILADOR_FINAL.bat
    echo 💡 Reinicia el símbolo del sistema si hay problemas
    echo.
) else (
    echo ⚠️ .NET SDK instalado pero no se detecta en esta sesión
    echo.
    echo 🔧 PASOS FINALES:
    echo ================
    echo 1. Cerrar esta ventana de comandos
    echo 2. Abrir nueva ventana como Administrador
    echo 3. Ejecutar COMPILADOR_FINAL.bat
    echo.
    echo 💡 El reinicio del símbolo del sistema es necesario
    echo    para actualizar las variables de entorno
    echo.
)

echo 📞 Soporte técnico: soporte@gridpos.com
echo.
pause
exit /b 0

:RefreshPath
REM Función para refrescar PATH en la sesión actual
for /f "tokens=2*" %%A in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v PATH 2^>nul') do set "SysPath=%%B"
for /f "tokens=2*" %%A in ('reg query "HKCU\Environment" /v PATH 2^>nul') do set "UserPath=%%B"
set "PATH=%SysPath%;%UserPath%"
goto :eof
