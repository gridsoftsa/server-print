@echo off
chcp 65001 >nul
echo ========================================
echo    GRIDPOS PRINT SERVICE - LOGS CORREGIDO
echo      🔧 Errores de compilación solucionados
echo ========================================
echo.

echo 🔧 CORRECCIONES APLICADAS:
echo ===========================
echo ✅ ESCPOS_NET actualizado a versión 3.0.0
echo ✅ Conflicto de namespace Color resuelto
echo ✅ Alias WinColor = System.Drawing.Color agregado
echo ✅ Tamaño ventana ajustado a 600x580px
echo ✅ Panel de logs compacto implementado
echo ✅ Todas las referencias Color corregidas
echo.

echo 📋 CARACTERÍSTICAS DEL SISTEMA DE LOGS:
echo =======================================
echo 🖥️ Panel compacto: 450x45px con scroll
echo 📝 Font Consolas para mejor legibilidad
echo 🗑️ Botón limpiar integrado (80x45px)
echo ⏱️ Timestamps automáticos [HH:mm:ss]
echo 🔄 Thread-safe para async operations
echo 📦 Logs de procesamiento de trabajos
echo.

echo 🎯 LOGS IMPLEMENTADOS:
echo ======================
echo 🚀 Inicio de servicio con configuración
echo 📦 Respuesta completa del API
echo 🔄 Procesamiento de cada trabajo
echo 🖨️ Detalles de impresión (nombre impresora)
echo 🗑️ Eliminación de trabajos de la cola
echo ❌ Errores y excepciones detallados
echo.

REM Verificar .NET
echo 🔍 Verificando .NET SDK...
dotnet --version >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: .NET SDK no está instalado
    echo.
    echo 📥 DESCARGAR .NET 6 SDK:
    echo    🌐 https://dotnet.microsoft.com/download/dotnet/6.0
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('dotnet --version') do set DOTNET_VERSION=%%i
echo ✅ .NET SDK detectado: %DOTNET_VERSION%
echo.

REM Limpiar compilaciones anteriores
echo 🧹 Limpiando compilaciones anteriores...
if exist bin rmdir /s /q bin >nul 2>&1
if exist obj rmdir /s /q obj >nul 2>&1
if exist GridPosPrintService.exe del GridPosPrintService.exe >nul 2>&1
echo ✅ Limpieza completada
echo.

REM Compilar
echo 🔨 COMPILANDO VERSIÓN CON LOGS CORREGIDOS...
echo ============================================
echo 📋 Correcciones técnicas:
echo    🔧 ESCPOS_NET 3.0.0 (compatible)
echo    🎨 WinColor alias para System.Drawing.Color
echo    📏 Ventana: 600x580px (compacta)
echo    📋 Panel logs: 450x45px (optimizado)
echo    🗑️ Botón limpiar: 80x45px (integrado)
echo    ⚡ Async/await thread-safe logging
echo.
echo 🔧 Iniciando compilación corregida...

dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o .

if %errorLevel% equ 0 (
    echo.
    echo ✅ ¡COMPILACIÓN EXITOSA - LOGS IMPLEMENTADOS!
    echo.

    if exist GridPosPrintService.exe (
        for %%A in (GridPosPrintService.exe) do set FILE_SIZE=%%~zA
        set /a FILE_SIZE_MB=%FILE_SIZE% / 1024 / 1024
        echo 🚀 Ejecutable creado: GridPosPrintService.exe (!FILE_SIZE_MB! MB)
        echo.

        echo 📦 CREANDO PAQUETE CON LOGS...
        echo.

        REM Manual de logs
        echo GRIDPOS PRINT SERVICE - SISTEMA DE LOGS > MANUAL_LOGS.txt
        echo =============================================== >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 📋 PANEL DE LOGS COMPACTO >> MANUAL_LOGS.txt
        echo ========================= >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 📏 Dimensiones: 450x45 píxeles >> MANUAL_LOGS.txt
        echo 📝 Font: Consolas 8pt ^(monospace^) >> MANUAL_LOGS.txt
        echo 📜 Scroll: Vertical automático >> MANUAL_LOGS.txt
        echo 🗑️ Botón limpiar: Lateral derecho >> MANUAL_LOGS.txt
        echo ⏱️ Timestamps: [HH:mm:ss] format >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 🔄 TIPOS DE LOGS IMPLEMENTADOS >> MANUAL_LOGS.txt
        echo =============================== >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 🚀 INICIO DE SERVICIO: >> MANUAL_LOGS.txt
        echo   [14:30:15] 🚀 Servicio iniciado: URL=https://api.gridpos.co/print-queue >> MANUAL_LOGS.txt
        echo   [14:30:15] ⏱️ Intervalo de monitoreo: 2 segundos >> MANUAL_LOGS.txt
        echo   [14:30:15] 🔑 Headers: Authorization=***, X-Client-Slug=mi-empresa >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 📦 CONSULTA API: >> MANUAL_LOGS.txt
        echo   [14:30:17] 📦 Respuesta API: [{"action":"salePrinter","id":"123",...}] >> MANUAL_LOGS.txt
        echo   [14:30:17] 🔄 Procesando 1 trabajos de impresión >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 🖨️ PROCESAMIENTO DE TRABAJOS: >> MANUAL_LOGS.txt
        echo   [14:30:17] 🔄 Procesando trabajo ID: 123, Acción: salePrinter >> MANUAL_LOGS.txt
        echo   [14:30:17] 🧾 Imprimiendo venta en: POS-80 >> MANUAL_LOGS.txt
        echo   [14:30:17] 📄 Imagen recibida: 50000 caracteres >> MANUAL_LOGS.txt
        echo   [14:30:17] 🖼️ Logo URL: https://empresa.com/logo.png >> MANUAL_LOGS.txt
        echo   [14:30:17] ✅ Venta impresa exitosamente en: POS-80 >> MANUAL_LOGS.txt
        echo   [14:30:17] 🗑️ Trabajo 123 eliminado de la cola >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo ❌ MANEJO DE ERRORES: >> MANUAL_LOGS.txt
        echo   [14:30:20] ❌ Error parsing JSON: Invalid JSON format >> MANUAL_LOGS.txt
        echo   [14:30:25] ⚠️ Error eliminando trabajo 456: NotFound >> MANUAL_LOGS.txt
        echo   [14:30:30] ❌ Sin conexión: HttpRequestException >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo 🎯 VENTAJAS DEL SISTEMA DE LOGS >> MANUAL_LOGS.txt
        echo ================================= >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo ✅ Monitoreo en tiempo real de operaciones >> MANUAL_LOGS.txt
        echo ✅ Identificación rápida de problemas >> MANUAL_LOGS.txt
        echo ✅ Trazabilidad completa de trabajos >> MANUAL_LOGS.txt
        echo ✅ Interfaz compacta sin sobrecargar ventana >> MANUAL_LOGS.txt
        echo ✅ Thread-safe para operaciones asíncronas >> MANUAL_LOGS.txt
        echo ✅ Scroll automático a entradas más recientes >> MANUAL_LOGS.txt
        echo ✅ Botón limpiar para gestión de memoria >> MANUAL_LOGS.txt
        echo. >> MANUAL_LOGS.txt
        echo ¡SISTEMA DE LOGS PROFESIONAL IMPLEMENTADO! 🚀 >> MANUAL_LOGS.txt

        REM Probador con logs
        echo @echo off > PROBAR_CON_LOGS.bat
        echo echo 🚀 Probando GridPos Print Service con Logs... >> PROBAR_CON_LOGS.bat
        echo echo. >> PROBAR_CON_LOGS.bat
        echo echo ✅ Ventana compacta: 600x580px >> PROBAR_CON_LOGS.bat
        echo echo 📋 Panel de logs: Esquina inferior >> PROBAR_CON_LOGS.bat
        echo echo 🗑️ Botón limpiar: Lateral derecho >> PROBAR_CON_LOGS.bat
        echo echo ⏱️ Timestamps automáticos visible >> PROBAR_CON_LOGS.bat
        echo echo 🔄 Logs en tiempo real funcionando >> PROBAR_CON_LOGS.bat
        echo echo. >> PROBAR_CON_LOGS.bat
        echo GridPosPrintService.exe >> PROBAR_CON_LOGS.bat

        echo    ✅ MANUAL_LOGS.txt - Documentación del sistema de logs
        echo    ✅ PROBAR_CON_LOGS.bat - Prueba con logs habilitados
        echo.
        echo ========================================
        echo      🎉 ¡VERSIÓN CON LOGS COMPLETADA!
        echo ========================================
        echo.
        echo 🔧 SOLUCIONES APLICADAS:
        echo    ✅ ESCPOS_NET 3.0.0 compatible
        echo    ✅ Namespace conflicts resueltos
        echo    ✅ WinColor alias implementado
        echo    ✅ Ventana compacta 600x580px
        echo    ✅ Panel logs optimizado
        echo.
        echo 📋 SISTEMA DE LOGS ACTIVO:
        echo    ⏱️ Timestamps automáticos
        echo    🔄 Logs thread-safe
        echo    📦 Trazabilidad completa
        echo    🗑️ Gestión de memoria
        echo    📜 Scroll automático
        echo.
        echo 🚀 FUNCIONALIDADES LOGS:
        echo    📡 Consultas API logueadas
        echo    🔄 Procesamiento de trabajos
        echo    🖨️ Detalles de impresión
        echo    ❌ Errores capturados
        echo    🗑️ Limpieza de cola
        echo.
        echo 📦 ARCHIVOS LISTOS PARA ENTREGA:
        echo    🚀 GridPosPrintService.exe ^(!FILE_SIZE_MB! MB^)
        echo    📖 MANUAL_LOGS.txt
        echo    🧪 PROBAR_CON_LOGS.bat
        echo.
        echo 🎯 PRÓXIMO PASO: Implementar impresión física real
        echo    🖨️ ESC/POS commands para impresoras compartidas
        echo    🔗 Conexión directa con Windows Print Spooler
        echo    📄 Procesamiento de imágenes base64
        echo.
        echo 🎉 ¡COMPILACIÓN EXITOSA - LISTA PARA PRUEBAS!
        echo.

    ) else (
        echo ❌ ERROR: No se generó el ejecutable
        echo    Revisar errores de compilación mostrados arriba
    )
) else (
    echo.
    echo ❌ ERROR EN LA COMPILACIÓN
    echo    Revisar errores mostrados arriba
    echo.
)

echo.
echo 🎉 COMPILADOR LOGS CORREGIDO - GridPos Print Service
echo 📧 Soporte técnico: soporte@gridpos.com
echo 📖 Manual de logs: MANUAL_LOGS.txt
echo.
pause
