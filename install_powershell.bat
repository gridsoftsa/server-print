@echo off
echo ========================================
echo    GridPos Printer Service Installer
echo    (Version PowerShell - Funciona en Windows 10/11)
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
copy "GridPosPrinter.ps1" "%INSTALL_DIR%\"
copy "GridPosPrinter.bat" "%INSTALL_DIR%\"

REM Crear directorio de logs
if not exist "%INSTALL_DIR%\logs" (
    mkdir "%INSTALL_DIR%\logs"
)

REM Crear script de inicio
echo Creando script de inicio...
(
echo @echo off
echo title GridPos Printer Service
echo color 0A
echo echo Iniciando GridPos Printer Service...
echo echo.
echo powershell -ExecutionPolicy Bypass -File "%%~dp0GridPosPrinter.ps1"
echo pause
) > "%INSTALL_DIR%\start_service.bat"

REM Crear tarea programada para inicio automático
echo Configurando inicio automático...
powershell -Command "& { $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument '-ExecutionPolicy Bypass -File \"C:\GridPos\GridPosPrinter.ps1\"'; $trigger = New-ScheduledTaskTrigger -AtStartup; Register-ScheduledTask -TaskName 'GridPosPrinterService' -Action $action -Trigger $trigger -RunLevel Highest -Force }"

REM Crear acceso directo en escritorio
echo Creando acceso directo...
powershell -Command "& { $WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%USERPROFILE%\Desktop\GridPos Printer.lnk'); $Shortcut.TargetPath = 'C:\GridPos\start_service.bat'; $Shortcut.WorkingDirectory = 'C:\GridPos'; $Shortcut.Save() }"

echo.
echo ========================================
echo    Instalación completada exitosamente
echo ========================================
echo.
echo Archivos instalados en: %INSTALL_DIR%
echo Servicio configurado para inicio automático
echo Acceso directo creado en el escritorio
echo.
echo Para iniciar manualmente:
echo 1. Doble clic en "GridPos Printer" del escritorio
echo 2. O ejecutar: C:\GridPos\start_service.bat
echo.
echo Para configurar:
echo 1. Editar: C:\GridPos\GridPosPrinter.ps1
echo 2. Cambiar: $ClientSlug = "tu-client-slug"
echo 3. Cambiar: $ApiUrl = "tu-url"
echo.
pause
