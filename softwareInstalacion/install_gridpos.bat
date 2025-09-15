@echo off
setlocal enabledelayedexpansion

REM ========================================
REM    INSTALADOR AUTOMATICO GRIDPOS NSSM
REM    Version: 1.0
REM    Fecha: 2025-09-10
REM ========================================

echo.
echo ========================================
echo    INSTALADOR AUTOMATICO GRIDPOS NSSM
echo ========================================
echo.

REM Verificar privilegios de administrador
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Este script requiere privilegios de administrador
    echo    Ejecuta como administrador y vuelve a intentar
    echo.
    pause
    exit /b 1
)

echo ✅ Privilegios de administrador verificados
echo.

REM ========================================
REM CARGAR CONFIGURACION
REM ========================================

REM Cargar variables desde config.bat si existe
if exist "%~dp0config.bat" (
    call "%~dp0config.bat"
    echo ✅ Configuración cargada desde config.bat
) else (
    echo ⚠️  Usando configuración por defecto
    REM Configuración por defecto
    set "INSTALL_DIR=C:\server-print"
    set "PHP_DIR=C:\php"
    set "COMPOSER_DIR=C:\composer"
    set "NSSM_DIR=C:\nssm"
    set "GIT_URL=https://github.com/gridsoftsa/server-print.git"
    set "SERVICE_NAME=gridpos"
)

echo [CONFIG] Directorio de instalación: %INSTALL_DIR%
echo [CONFIG] PHP: %PHP_DIR%
echo [CONFIG] Composer: %COMPOSER_DIR%
echo [CONFIG] NSSM: %NSSM_DIR%
echo [CONFIG] Servicio: %SERVICE_NAME%
echo.

REM ========================================
REM PASO 1: VERIFICAR/INSTALAR GIT
REM ========================================

echo [1/8] Verificando Git...
git --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Git no está instalado
    echo    Instalando Git desde el archivo incluido...

    if exist "%~dp0Git-2.51.0-64-bit.exe" (
        echo    Ejecutando instalador de Git...
        "%~dp0Git-2.51.0-64-bit.exe" /SILENT /NORESTART

        REM Agregar Git al PATH temporalmente
        set "PATH=%PATH%;C:\Program Files\Git\bin"

        echo ✅ Git instalado correctamente
    ) else (
        echo ❌ Archivo Git-2.51.0-64-bit.exe no encontrado
        echo    Descarga Git manualmente desde https://git-scm.com/
        pause
        exit /b 1
    )
) else (
    echo ✅ Git ya está instalado
)
echo.

REM ========================================
REM PASO 2: INSTALAR PHP
REM ========================================

echo [2/8] Instalando PHP 8.2...
if not exist "%PHP_DIR%" (
    mkdir "%PHP_DIR%"
)

if exist "%~dp0php-8.2.zip" (
    echo    Extrayendo PHP 8.2...
    powershell -command "Expand-Archive -Path '%~dp0php-8.2.zip' -DestinationPath '%PHP_DIR%' -Force"

    REM Configurar php.ini básico
    if not exist "%PHP_DIR%\php.ini" (
        copy "%PHP_DIR%\php.ini-production" "%PHP_DIR%\php.ini"
    )

    REM Habilitar extensiones necesarias
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=openssl', 'extension=openssl' | Set-Content '%PHP_DIR%\php.ini'"
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=curl', 'extension=curl' | Set-Content '%PHP_DIR%\php.ini'"
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=mbstring', 'extension=mbstring' | Set-Content '%PHP_DIR%\php.ini'"
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=pdo_mysql', 'extension=pdo_mysql' | Set-Content '%PHP_DIR%\php.ini'"
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=mysqli', 'extension=mysqli' | Set-Content '%PHP_DIR%\php.ini'"
    powershell -command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=zip', 'extension=zip' | Set-Content '%PHP_DIR%\php.ini'"

    echo ✅ PHP 8.2 instalado y configurado
) else (
    echo ❌ Archivo php-8.2.zip no encontrado
    pause
    exit /b 1
)

REM Agregar PHP al PATH del sistema
setx PATH "%PATH%;%PHP_DIR%" /M >nul 2>&1
set "PATH=%PATH%;%PHP_DIR%"
echo.

REM ========================================
REM PASO 3: INSTALAR COMPOSER
REM ========================================

echo [3/8] Instalando Composer...
if exist "%~dp0Composer-Setup.exe" (
    echo    Ejecutando instalador de Composer...
    "%~dp0Composer-Setup.exe" /SILENT /NORESTART
    echo ✅ Composer instalado
) else (
    echo ❌ Archivo Composer-Setup.exe no encontrado
    pause
    exit /b 1
)
echo.

REM ========================================
REM PASO 4: CLONAR REPOSITORIO
REM ========================================

echo [4/8] Clonando repositorio...
if exist "%INSTALL_DIR%" (
    echo    Eliminando directorio existente...
    rmdir /s /q "%INSTALL_DIR%"
)

echo    Clonando desde repositorio...
REM Cambiar esta URL por la correcta de tu repositorio
git clone https://github.com/gridsoftsa/server-print.git "%INSTALL_DIR%"
if %errorlevel% neq 0 (
    echo ❌ Error al clonar el repositorio
    echo    Verifica la URL del repositorio y tu conexión a internet
    pause
    exit /b 1
)
echo ✅ Repositorio clonado correctamente
echo.

REM ========================================
REM PASO 5: INSTALAR DEPENDENCIAS
REM ========================================

echo [5/8] Instalando dependencias de PHP...
cd /d "%INSTALL_DIR%"

REM Verificar que composer esté disponible
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Composer no está disponible en PATH
    echo    Reinicia el sistema y ejecuta el script nuevamente
    pause
    exit /b 1
)

echo    Ejecutando composer install...
composer install --no-dev --optimize-autoloader
if %errorlevel% neq 0 (
    echo ❌ Error al instalar dependencias de Composer
    pause
    exit /b 1
)

echo ✅ Dependencias instaladas correctamente
echo.

REM ========================================
REM PASO 6: CONFIGURAR LARAVEL
REM ========================================

echo [6/8] Configurando Laravel...

REM Copiar archivo de configuración
if not exist ".env" (
    if exist ".env.example" (
        copy ".env.example" ".env"
        echo    Archivo .env creado desde .env.example
    ) else (
        echo ❌ No se encontró .env.example
        pause
        exit /b 1
    )
)

REM Generar clave de aplicación
php artisan key:generate --force
if %errorlevel% neq 0 (
    echo ❌ Error al generar la clave de aplicación
    pause
    exit /b 1
)

echo ✅ Laravel configurado correctamente
echo.

REM ========================================
REM PASO 7: INSTALAR NSSM
REM ========================================

echo [7/8] Configurando NSSM...
if not exist "%NSSM_DIR%" (
    mkdir "%NSSM_DIR%"
)

if exist "%~dp0nssm\nssm.exe" (
    copy "%~dp0nssm\nssm.exe" "%NSSM_DIR%\nssm.exe"
    echo ✅ NSSM copiado a %NSSM_DIR%
) else (
    echo ❌ Archivo nssm.exe no encontrado
    pause
    exit /b 1
)

REM Agregar NSSM al PATH del sistema
setx PATH "%PATH%;%NSSM_DIR%" /M >nul 2>&1
set "PATH=%PATH%;%NSSM_DIR%"
echo.

REM ========================================
REM PASO 8: CREAR SERVICIO GRIDPOS
REM ========================================

echo [8/8] Creando servicio GridPOS...

REM Verificar si el servicio ya existe y eliminarlo
"%NSSM_DIR%\nssm.exe" status %SERVICE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo    Servicio existente encontrado, eliminando...
    "%NSSM_DIR%\nssm.exe" stop %SERVICE_NAME% >nul 2>&1
    "%NSSM_DIR%\nssm.exe" remove %SERVICE_NAME% confirm >nul 2>&1
)

REM Crear el servicio
echo    Creando servicio %SERVICE_NAME%...
"%NSSM_DIR%\nssm.exe" install %SERVICE_NAME% "%PHP_DIR%\php.exe" "artisan" "ws:listen"
if %errorlevel% neq 0 (
    echo ❌ Error al crear el servicio
    pause
    exit /b 1
)

REM Configurar el servicio
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% AppDirectory "%INSTALL_DIR%"
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% DisplayName "GridPOS WebSocket Service"
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% Description "Servicio WebSocket para GridPOS"
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% Start SERVICE_AUTO_START

REM Configurar logs
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% AppStdout "%INSTALL_DIR%\storage\logs\gridpos.log"
"%NSSM_DIR%\nssm.exe" set %SERVICE_NAME% AppStderr "%INSTALL_DIR%\storage\logs\gridpos-error.log"

REM Iniciar el servicio
echo    Iniciando servicio...
"%NSSM_DIR%\nssm.exe" start %SERVICE_NAME%
if %errorlevel% neq 0 (
    echo ❌ Error al iniciar el servicio
    echo    Revisa los logs en %INSTALL_DIR%\storage\logs\
    pause
    exit /b 1
)

echo ✅ Servicio GridPOS creado e iniciado correctamente
echo.

REM ========================================
REM VERIFICACION FINAL
REM ========================================

echo ========================================
echo    INSTALACION COMPLETADA EXITOSAMENTE
echo ========================================
echo.
echo ✅ PHP 8.2 instalado en: %PHP_DIR%
echo ✅ Composer instalado
echo ✅ Proyecto clonado en: %INSTALL_DIR%
echo ✅ Dependencias instaladas
echo ✅ Laravel configurado
echo ✅ NSSM instalado en: %NSSM_DIR%
echo ✅ Servicio '%SERVICE_NAME%' creado e iniciado
echo.
echo 📁 Directorio del proyecto: %INSTALL_DIR%
echo 🔧 Comando para gestionar servicio: nssm.exe [start|stop|restart|status] %SERVICE_NAME%
echo 📋 Logs del servicio: %INSTALL_DIR%\storage\logs\
echo.
echo ========================================
echo    COMANDOS UTILES
echo ========================================
echo.
echo # Verificar estado del servicio:
echo nssm status %SERVICE_NAME%
echo.
echo # Reiniciar servicio:
echo nssm restart %SERVICE_NAME%
echo.
echo # Ver logs:
echo type "%INSTALL_DIR%\storage\logs\gridpos.log"
echo.
echo # Editar configuracion del servicio:
echo nssm edit %SERVICE_NAME%
echo.

REM Crear script de gestion rapida
echo @echo off > "%INSTALL_DIR%\manage_service.bat"
echo echo GridPOS Service Manager >> "%INSTALL_DIR%\manage_service.bat"
echo echo ======================= >> "%INSTALL_DIR%\manage_service.bat"
echo echo 1. Status: nssm status %SERVICE_NAME% >> "%INSTALL_DIR%\manage_service.bat"
echo echo 2. Start:  nssm start %SERVICE_NAME% >> "%INSTALL_DIR%\manage_service.bat"
echo echo 3. Stop:   nssm stop %SERVICE_NAME% >> "%INSTALL_DIR%\manage_service.bat"
echo echo 4. Restart: nssm restart %SERVICE_NAME% >> "%INSTALL_DIR%\manage_service.bat"
echo echo. >> "%INSTALL_DIR%\manage_service.bat"
echo nssm status %SERVICE_NAME% >> "%INSTALL_DIR%\manage_service.bat"
echo pause >> "%INSTALL_DIR%\manage_service.bat"

echo 📄 Script de gestión creado: %INSTALL_DIR%\manage_service.bat
echo.
echo Presiona cualquier tecla para finalizar...
pause >nul

endlocal
