@echo off
echo ========================================
echo    GridPos Printer Service Installer
echo ========================================
echo.

REM Crear directorio de instalación
set "INSTALL_DIR=C:\GridPos"
if not exist "%INSTALL_DIR%" (
    echo Creando directorio de instalación...
    mkdir "%INSTALL_DIR%"
)

REM Copiar archivos
echo Copiando archivos...
copy "GridPosPrinter.exe" "%INSTALL_DIR%\"
copy "GridPosPrinter.exe.config" "%INSTALL_DIR%\"

REM Crear directorio de logs
if not exist "%INSTALL_DIR%\logs" (
    mkdir "%INSTALL_DIR%\logs"
)

REM Crear tarea programada para inicio automático
echo Configurando inicio automático...
schtasks /create /tn "GridPosPrinterService" /tr "%INSTALL_DIR%\GridPosPrinter.exe" /sc onstart /ru "SYSTEM" /f

REM Crear acceso directo en escritorio
echo Creando acceso directo...
powershell -Command "$WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%USERPROFILE%\Desktop\GridPos Printer.lnk'); $Shortcut.TargetPath = '%INSTALL_DIR%\GridPosPrinter.exe'; $Shortcut.Save()"

echo.
echo ========================================
echo    Instalación completada exitosamente
echo ========================================
echo.
echo Archivos instalados en: %INSTALL_DIR%
echo Servicio configurado para inicio automático
echo Acceso directo creado en el escritorio
echo.
echo Para iniciar manualmente: %INSTALL_DIR%\GridPosPrinter.exe
echo.
pause
