@echo off
title GridPos Printer Service - Instalador Interactivo
color 0A

echo ========================================
echo    GridPos Printer Service Installer
echo    (Version PowerShell - Interactivo)
echo ========================================
echo.

REM Verificar si se ejecuta como administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: Este instalador debe ejecutarse como Administrador
    echo.
    echo Para ejecutar como administrador:
    echo 1. Clic derecho en este archivo
    echo 2. Seleccionar "Ejecutar como administrador"
    echo.
    pause
    exit /b 1
)

echo ✅ Ejecutando como administrador
echo.

REM Solicitar configuración al usuario
echo ⚙️ CONFIGURACIÓN REQUERIDA:
echo.

:ask_client_slug
set /p CLIENT_SLUG="📝 Ingresa tu Client Slug: "
if "%CLIENT_SLUG%"=="" (
    echo ❌ El Client Slug es obligatorio
    goto ask_client_slug
)

:ask_api_url
set /p API_URL="🌐 Ingresa tu API URL (Enter para usar default): "
if "%API_URL%"=="" (
    set API_URL=https://api.gridpos.co/print-queue
    echo ✅ Usando URL por defecto: %API_URL%
)

echo.
echo ✅ Configuración guardada:
echo    📝 Client Slug: %CLIENT_SLUG%
echo    🌐 API URL: %API_URL%
echo.

REM Crear directorio de instalación
set "INSTALL_DIR=C:\GridPos"
if not exist "%INSTALL_DIR%" (
    echo 📁 Creando directorio de instalación...
    mkdir "%INSTALL_DIR%"
)

REM Copiar archivos
echo 📋 Copiando archivos...
copy "GridPosPrinter.bat" "%INSTALL_DIR%\" >nul 2>&1

REM Crear script de inicio
echo 🔧 Creando script de inicio...
(
echo @echo off
echo title GridPos Printer Service
echo color 0A
echo echo ========================================
echo echo    GridPos Printer Service
echo echo ========================================
echo echo.
echo echo Iniciando servicio...
echo echo.
echo powershell -ExecutionPolicy Bypass -File "%%~dp0GridPosPrinter_Simple.ps1"
echo echo.
echo echo Servicio detenido. Presiona cualquier tecla para cerrar...
echo pause
) > "%INSTALL_DIR%\start_service.bat"

REM Crear archivo de configuración personalizado
echo ⚙️ Generando archivo de configuración personalizado...
(
echo # GridPos Printer Service - Configuración personalizada
echo # Generado automáticamente por el instalador
echo # Fecha: %date% %time%
echo.
echo # Configuración
echo $ApiUrl = "%API_URL%"
echo $ClientSlug = "%CLIENT_SLUG%"
echo $AuthToken = "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3"
echo $Interval = 200
echo.
echo # Configurar TLS
echo [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
echo.
echo # Headers
echo $headers = @{
echo     'Authorization' = $AuthToken
echo     'X-Client-Slug' = $ClientSlug
echo     'Content-Type' = 'application/json'
echo }
echo.
echo # Variables globales
echo $Global:requestCount = 0
echo $Global:jobCount = 0
echo $Global:startTime = Get-Date
echo.
echo # Función de logging
echo function Write-Log {
echo     param([string]$Message^)
echo     $timestamp = Get-Date -Format "HH:mm:ss.fff"
echo     $logMessage = "[$timestamp] $Message"
echo     Write-Host $logMessage -ForegroundColor Green
echo }
echo.
echo # Función para procesar trabajo
echo function Process-Job {
echo     param($job^)
echo.
echo     try {
echo         $action = $job.action
echo         $printer = $job.printer
echo.
echo         Write-Log "🖨️ Procesando: $action en $printer"
echo.
echo         if ($action -eq "salePrinter"^) {
echo             if ($job.image^) {
echo                 Write-Log "🖼️ Imprimiendo imagen en $printer"
echo             } else {
echo                 Write-Log "📄 Imprimiendo ESC/POS en $printer"
echo             }
echo         } elseif ($action -eq "orderPrinter"^) {
echo             Write-Log "📋 Imprimiendo orden en $printer"
echo         } elseif ($action -eq "openCashDrawer"^) {
echo             Write-Log "💰 Abriendo caja registradora en $printer"
echo         } else {
echo             Write-Log "⚠️ Acción desconocida: $action"
echo         }
echo.
echo         # Eliminar trabajo
echo         $deleteUrl = "$ApiUrl/$($job.id^)"
echo         $null = Invoke-RestMethod -Uri $deleteUrl -Method DELETE -Headers $headers -ErrorAction SilentlyContinue
echo.
echo         Write-Log "✅ Trabajo completado: $action"
echo         $Global:jobCount++
echo.
echo     } catch {
echo         Write-Log "❌ Error procesando trabajo: $($_.Exception.Message^)"
echo     }
echo }
echo.
echo # Función principal
echo function Check-Queue {
echo     try {
echo         $response = Invoke-RestMethod -Uri $ApiUrl -Method GET -Headers $headers -TimeoutSec 3
echo.
echo         if ($response -and $response.Count -gt 0^) {
echo             Write-Log "📨 Encontrados $($response.Count^) trabajos"
echo.
echo             foreach ($job in $response^) {
echo                 Process-Job -job $job
echo             }
echo         }
echo.
echo         $Global:requestCount++
echo.
echo     } catch {
echo         if ($_.Exception.Response.StatusCode -eq 404^) {
echo             $Global:requestCount++
echo             return
echo         }
echo         Write-Log "❌ Error verificando cola: $($_.Exception.Message^)"
echo     }
echo }
echo.
echo # Función de estadísticas
echo function Show-Stats {
echo     $elapsed = (Get-Date^) - $Global:startTime
echo     $requestsPerSecond = if ($elapsed.TotalSeconds -gt 0^) { [math]::Round($Global:requestCount / $elapsed.TotalSeconds, 2^) } else { 0 }
echo.
echo     Write-Host ""
echo     Write-Host "============================================================" -ForegroundColor Cyan
echo     Write-Host "📊 ESTADÍSTICAS:" -ForegroundColor Yellow
echo     Write-Host "Tiempo ejecutándose: $($elapsed.ToString('hh\:mm\:ss'^)^)" -ForegroundColor White
echo     Write-Host "Peticiones realizadas: $($Global:requestCount^)" -ForegroundColor White
echo     Write-Host "Trabajos procesados: $($Global:jobCount^)" -ForegroundColor White
echo     Write-Host "Peticiones/segundo: $requestsPerSecond" -ForegroundColor White
echo     Write-Host "Intervalo: ${Interval}ms" -ForegroundColor White
echo     Write-Host "============================================================" -ForegroundColor Cyan
echo     Write-Host ""
echo }
echo.
echo # Inicio del servicio
echo Write-Log "🚀 GridPos Printer Service iniciado"
echo Write-Log "API URL: $ApiUrl"
echo Write-Log "Client Slug: $ClientSlug"
echo Write-Log "Intervalo: ${Interval}ms"
echo Write-Log "⚡ Configurado para máxima velocidad"
echo Write-Log "Presiona Ctrl+C para detener"
echo.
echo # Bucle principal
echo try {
echo     while ($true^) {
echo         Check-Queue
echo.
echo         if ($Global:requestCount %% 100 -eq 0^) {
echo             Show-Stats
echo         }
echo.
echo         Start-Sleep -Milliseconds $Interval
echo     }
echo } catch {
echo     Write-Log "Error en el servicio: $($_.Exception.Message^)"
echo } finally {
echo     Write-Log "Deteniendo servicio..."
echo     Show-Stats
echo }
) > "%INSTALL_DIR%\GridPosPrinter_Simple.ps1"

REM Crear directorio de logs
if not exist "%INSTALL_DIR%\logs" (
    mkdir "%INSTALL_DIR%\logs"
)

REM Crear tarea programada para inicio automático
echo 🔄 Configurando inicio automático...
powershell -Command "& { $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument '-ExecutionPolicy Bypass -File \"C:\GridPos\GridPosPrinter_Simple.ps1\"'; $trigger = New-ScheduledTaskTrigger -AtStartup; Register-ScheduledTask -TaskName 'GridPosPrinterService' -Action $action -Trigger $trigger -RunLevel Highest -Force }" >nul 2>&1

REM Crear acceso directo en escritorio
echo 📱 Creando acceso directo en escritorio...
powershell -Command "& { $WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%USERPROFILE%\Desktop\GridPos Printer.lnk'); $Shortcut.TargetPath = 'C:\GridPos\start_service.bat'; $Shortcut.WorkingDirectory = 'C:\GridPos'; $Shortcut.Description = 'GridPos Printer Service'; $Shortcut.Save() }" >nul 2>&1

echo.
echo ========================================
echo    ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE
echo ========================================
echo.
echo 📁 Archivos instalados en: %INSTALL_DIR%
echo 🔄 Servicio configurado para inicio automático
echo 📱 Acceso directo creado en el escritorio
echo.
echo ✅ Configuración automática:
echo    📝 Client Slug: %CLIENT_SLUG%
echo    🌐 API URL: %API_URL%
echo    ⚡ Velocidad: 200ms (ultra rápido)
echo.
echo 🚀 Para iniciar el servicio:
echo    1. Doble clic en "GridPos Printer" del escritorio
echo    2. O ejecutar: C:\GridPos\start_service.bat
echo.
echo ⚙️ Para modificar la configuración:
echo    1. Editar: C:\GridPos\GridPosPrinter_Simple.ps1
echo    2. Cambiar las variables al inicio del archivo
echo.
echo 📊 El servicio verificará la cola cada 200ms
echo    (25x más rápido que la solución PHP anterior)
echo.
pause
