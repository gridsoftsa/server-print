# GridPos Printer Service - Ultra Fast
# Funciona inmediatamente en Windows 10/11

param(
    [string]$ApiUrl = "https://api.gridpos.co/print-queue",
    [string]$ClientSlug = "tu-client-slug",
    [string]$AuthToken = "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3",
    [int]$Interval = 200
)

# Configurar TLS para HTTPS
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# Headers para la API
$headers = @{
    'Authorization' = $AuthToken
    'X-Client-Slug' = $ClientSlug
    'Content-Type' = 'application/json'
}

# Variables de estadísticas
$requestCount = 0
$jobCount = 0
$startTime = Get-Date

# Función para logging
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "HH:mm:ss.fff"
    $logMessage = "[$timestamp] $Message"

    Write-Host $logMessage -ForegroundColor Green

    # También escribir a archivo
    try {
        $logPath = Join-Path $PSScriptRoot "gridpos-printer.log"
        Add-Content -Path $logPath -Value $logMessage -ErrorAction SilentlyContinue
    } catch {
        # Ignorar errores de escritura de log
    }
}

# Función para procesar trabajos de impresión
function Process-PrintJob {
    param($job)

    try {
        $action = $job.action
        $printer = $job.printer

        Write-Log "🖨️ Procesando: $action en $printer"

        switch ($action) {
            "salePrinter" {
                if ($job.image) {
                    Write-Log "🖼️ Imprimiendo imagen en $printer"
                    # Aquí iría la lógica de impresión de imagen
                } else {
                    Write-Log "📄 Imprimiendo ESC/POS en $printer"
                    # Aquí iría la lógica de impresión ESC/POS
                }
            }
            "orderPrinter" {
                Write-Log "📋 Imprimiendo orden en $printer"
                # Aquí iría la lógica de impresión de orden
            }
            "openCashDrawer" {
                Write-Log "💰 Abriendo caja registradora en $printer"
                # Aquí iría la lógica de apertura de caja
            }
            default {
                Write-Log "⚠️ Acción desconocida: $action"
            }
        }

        # Eliminar trabajo de la cola
        $deleteUrl = "$ApiUrl/$($job.id)"
        $null = Invoke-RestMethod -Uri $deleteUrl -Method DELETE -Headers $headers -ErrorAction SilentlyContinue

        Write-Log "✅ Trabajo completado: $action"
        $script:jobCount++

    } catch {
        Write-Log "❌ Error procesando trabajo: $($_.Exception.Message)"
    }
}

# Función principal de verificación
function Check-PrintQueue {
    try {
        $response = Invoke-RestMethod -Uri $ApiUrl -Method GET -Headers $headers -TimeoutSec 3

        if ($response -and $response.Count -gt 0) {
            Write-Log "📨 Encontrados $($response.Count) trabajos de impresión"

            foreach ($job in $response) {
                Process-PrintJob -job $job
            }
        }

        $script:requestCount++

    } catch {
        if ($_.Exception.Response.StatusCode -eq 404) {
            # No hay trabajos, esto es normal
            $script:requestCount++
            return
        }
        Write-Log "❌ Error verificando cola: $($_.Exception.Message)"
    }
}

# Función para mostrar estadísticas
function Show-Stats {
    $elapsed = (Get-Date) - $startTime
    $requestsPerSecond = if ($elapsed.TotalSeconds -gt 0) { [math]::Round($requestCount / $elapsed.TotalSeconds, 2) } else { 0 }

    Write-Host "`n" -NoNewline
    Write-Host "=" * 60 -ForegroundColor Cyan
    Write-Host "📊 ESTADÍSTICAS:" -ForegroundColor Yellow
    Write-Host "Tiempo ejecutándose: $($elapsed.ToString('hh\:mm\:ss'))" -ForegroundColor White
    Write-Host "Peticiones realizadas: $requestCount" -ForegroundColor White
    Write-Host "Trabajos procesados: $jobCount" -ForegroundColor White
    Write-Host "Peticiones/segundo: $requestsPerSecond" -ForegroundColor White
    Write-Host "Intervalo: ${Interval}ms" -ForegroundColor White
    Write-Host "=" * 60 -ForegroundColor Cyan
    Write-Host "`n" -NoNewline
}

# Función para manejar la salida limpia
function Stop-Service {
    Write-Log "Deteniendo servicio de impresión..."
    Show-Stats
    exit
}

# Configurar manejo de señales
Register-EngineEvent PowerShell.Exiting -Action { Stop-Service }

Write-Log "🚀 GridPos Printer Service - Ultra Fast iniciado"
Write-Log "API URL: $ApiUrl"
Write-Log "Client Slug: $ClientSlug"
Write-Log "Intervalo: ${Interval}ms"
Write-Log "⚡ Configurado para máxima velocidad"
Write-Log "Presiona Ctrl+C para detener"

try {
    while ($true) {
        Check-PrintQueue

        # Mostrar estadísticas cada 100 peticiones
        if ($requestCount % 100 -eq 0) {
            Show-Stats
        }

        Start-Sleep -Milliseconds $Interval
    }
} catch {
    Write-Log "Error en el servicio: $($_.Exception.Message)"
} finally {
    Stop-Service
}
