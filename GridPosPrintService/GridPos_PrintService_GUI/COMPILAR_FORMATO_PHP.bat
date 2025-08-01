@echo off
chcp 65001 >nul
echo ========================================
echo    GRIDPOS PRINT SERVICE - FORMATO PHP
echo      🖨️ Impresión IDÉNTICA al PrinterController.php
echo ========================================
echo.

echo 🎯 FORMATO IDENTICAL AL PHP IMPLEMENTADO:
echo =========================================
echo ✅ printOrderWithEscPos() replicado al 100%%
echo ✅ Soporte papel 58mm y 80mm dinámico
echo ✅ Encabezado: Cliente + fecha + teléfono + dirección
echo ✅ Separadores: 32 chars (58mm) / 48 chars (80mm)
echo ✅ Productos: Cantidad + nombre + notas optimizadas
echo ✅ WordWrap texto igual al PHP (28 chars para 58mm)
echo ✅ Pie de página: Usuario + timestamp + orden ID
echo ✅ Apertura caja integrada con open_cash
echo.

echo 📏 SOPORTE DINÁMICO DE PAPEL (IGUAL AL PHP):
echo =============================================
echo 📱 PAPEL 58MM:
echo    - Cliente: MODE_EMPHASIZED solo (texto moderado)
echo    - Límite nombre: 32 caracteres, truncar resto
echo    - Separador: 32 guiones (----)
echo    - Headers: "CANT  ITEM" (compacto)
echo    - Productos: qty.PadRight(2) + 28 chars nombre
echo    - Notas: WordWrap a 28 chars, "  * " prefijo
echo    - Nombres largos: Línea adicional con "    " indent
echo.
echo 🖨️ PAPEL 80MM:
echo    - Cliente: MODE_DOUBLE_WIDTH + MODE_EMPHASIZED
echo    - Separador: 48 guiones (----)
echo    - Headers: "CANT     ITEM" (normal)
echo    - Productos: qty.PadRight(2) + nombre completo
echo    - Notas: "    * " prefijo directo
echo.

echo 🔧 ESTRUCTURA EXACTA AL PHP:
echo ============================
echo 1. 📋 Extraer print_settings.paper_width
echo 2. 🎯 Calcular isSmallPaper = (paperWidth == 58)
echo 3. 🏷️ Cliente centrado con formato dinámico
echo 4. 📅 Fecha de orden
echo 5. 📞 Teléfono si existe (CEL: {phone})
echo 6. 📍 Dirección envío si existe (DIRECCION: {address})
echo 7. ➖ Separador grueso (32/48 chars)
echo 8. 📝 Headers columnas adaptados
echo 9. 🛒 Productos con formato específico por papel
echo 10. 📝 Notas con WordWrap optimizado
echo 11. ➖ Separador final
echo 12. 💬 Nota general si existe
echo 13. 👤 Usuario que atiende
echo 14. 🕐 Timestamp impresión
echo 15. 🔢 ID orden (shipping_address ? order_number : id)
echo 16. ✂️ Corte papel
echo 17. 💰 Apertura caja opcional
echo.

echo 📝 LOGS DETALLADOS ESPECÍFICOS:
echo ===============================
echo "📝 Generando ticket ESC/POS IGUAL AL PHP..."
echo "🚀 Ancho de papel: {paperWidth}"
echo "🚀 Orden impresa con ESC/POS en {ms}ms (ULTRA RÁPIDO)"
echo "💰 Caja abierta como parte del proceso de impresión ESC/POS"
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
echo 🔨 COMPILANDO CON FORMATO IDÉNTICO AL PHP...
echo ============================================
echo 📋 Equivalencias implementadas:
echo    PHP: $printer->selectPrintMode(Printer::MODE_EMPHASIZED)
echo    C#:  e.SetStyles(PrintStyle.Bold)
echo.
echo    PHP: $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH)
echo    C#:  e.SetStyles(PrintStyle.Bold ^| PrintStyle.DoubleWidth)
echo.
echo    PHP: $printer->text($separator . "\n")
echo    C#:  printer.Write(e.PrintLine(separator))
echo.
echo    PHP: $printer->pulse()
echo    C#:  printer.Write(e.OpenCashDrawerPin2())
echo.
echo    PHP: $printer->cut()
echo    C#:  printer.Write(e.FullCutAfterFeed(1))
echo.
echo    PHP: wordWrapEscPos($notes, $maxNoteChars)
echo    C#:  WordWrapText(notes, maxNoteChars)
echo.
echo 🔧 Iniciando compilación formato PHP...

dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o .

if %errorLevel% equ 0 (
    echo.
    echo ✅ ¡COMPILACIÓN EXITOSA - FORMATO PHP REPLICADO!
    echo.

    if exist GridPosPrintService.exe (
        for %%A in (GridPosPrintService.exe) do set FILE_SIZE=%%~zA
        set /a FILE_SIZE_MB=%FILE_SIZE% / 1024 / 1024
        echo 🚀 Ejecutable creado: GridPosPrintService.exe (!FILE_SIZE_MB! MB)
        echo.

        echo 📦 CREANDO PAQUETE FORMATO PHP...
        echo.

        REM Manual de equivalencias
        echo GRIDPOS PRINT SERVICE - FORMATO IDÉNTICO AL PHP > MANUAL_FORMATO_PHP.txt
        echo ======================================================== >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 🖨️ REPLICACIÓN EXACTA DEL PRINTERCONTROLLER.PHP >> MANUAL_FORMATO_PHP.txt
        echo ================================================= >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 📋 MÉTODO REPLICADO: printOrderWithEscPos() >> MANUAL_FORMATO_PHP.txt
        echo ================================== >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 🔧 EQUIVALENCIAS PHP ^<-^> C#: >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 1. 📏 DETECCIÓN PAPEL: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $paperWidth = $orderData['print_settings']['paper_width'] ?? 80; >> MANUAL_FORMATO_PHP.txt
        echo    C#:  orderData.TryGetProperty("print_settings"^) paperWidth = 80; >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 2. 📱 PAPEL PEQUEÑO: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $isSmallPaper = $paperWidth == 58; >> MANUAL_FORMATO_PHP.txt
        echo    C#:  var isSmallPaper = paperWidth == 58; >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 3. 🏷️ CLIENTE FORMATO: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $printer-^>selectPrintMode(Printer::MODE_EMPHASIZED^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  e.SetStyles(PrintStyle.Bold^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $printer-^>selectPrintMode(Printer::MODE_DOUBLE_WIDTH ^| Printer::MODE_EMPHASIZED^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  e.SetStyles(PrintStyle.Bold ^| PrintStyle.DoubleWidth^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 4. ➖ SEPARADORES: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $separator = $isSmallPaper ? str_repeat('-', 32^) : str_repeat('-', 48^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  var separator = isSmallPaper ? new string('-', 32^) : new string('-', 48^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 5. 📝 HEADERS COLUMNAS: >> MANUAL_FORMATO_PHP.txt
        echo    PHP 58mm: $printer-^>text("CANT  ITEM\n"^); >> MANUAL_FORMATO_PHP.txt
        echo    C# 58mm:  printer.Write(e.PrintLine("CANT  ITEM"^)^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo    PHP 80mm: $printer-^>text("CANT     ITEM\n"^); >> MANUAL_FORMATO_PHP.txt
        echo    C# 80mm:  printer.Write(e.PrintLine("CANT     ITEM"^)^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 6. 🛒 PRODUCTOS FORMATO: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $qtyPadded = str_pad($qty, 2, ' ', STR_PAD_RIGHT^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  var qtyPadded = qty.ToString(^).PadRight(2^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $maxNameChars = 28; $nameFormatted = strlen($name^) ^> $maxNameChars ? >> MANUAL_FORMATO_PHP.txt
        echo         substr($name, 0, $maxNameChars^) : $name; >> MANUAL_FORMATO_PHP.txt
        echo    C#:  var maxNameChars = 28; var nameFormatted = name.Length ^> maxNameChars ? >> MANUAL_FORMATO_PHP.txt
        echo         name.Substring(0, maxNameChars^) : name; >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 7. 📝 WORD WRAP NOTAS: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $noteLines = $this-^>wordWrapEscPos($notes, $maxNoteChars^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  var noteLines = WordWrapText(notes, maxNoteChars^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 8. 💰 APERTURA CAJA: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $printer-^>pulse(^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  printer.Write(e.OpenCashDrawerPin2(^)^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 9. ✂️ CORTE PAPEL: >> MANUAL_FORMATO_PHP.txt
        echo    PHP: $printer-^>cut(^); >> MANUAL_FORMATO_PHP.txt
        echo    C#:  printer.Write(e.FullCutAfterFeed(1^)^); >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 🎯 RESULTADO FINAL >> MANUAL_FORMATO_PHP.txt
        echo ================== >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo ✅ Ticket impreso IDÉNTICO al PHP >> MANUAL_FORMATO_PHP.txt
        echo ✅ Mismo formato para papel 58mm y 80mm >> MANUAL_FORMATO_PHP.txt
        echo ✅ Misma estructura de encabezado y pie >> MANUAL_FORMATO_PHP.txt
        echo ✅ Mismo manejo de notas con WordWrap >> MANUAL_FORMATO_PHP.txt
        echo ✅ Misma lógica de apertura de caja >> MANUAL_FORMATO_PHP.txt
        echo ✅ Logs específicos del proceso >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo 🚀 vs Implementación anterior: >> MANUAL_FORMATO_PHP.txt
        echo ✅ 100%% fidelidad al PrinterController.php >> MANUAL_FORMATO_PHP.txt
        echo ✅ Soporte dinámico papel 58/80mm >> MANUAL_FORMATO_PHP.txt
        echo ✅ WordWrap optimizado para notas largas >> MANUAL_FORMATO_PHP.txt
        echo ✅ Headers y separadores adaptativos >> MANUAL_FORMATO_PHP.txt
        echo ✅ Formato productos idéntico >> MANUAL_FORMATO_PHP.txt
        echo. >> MANUAL_FORMATO_PHP.txt
        echo ¡IMPRESIÓN IDÉNTICA AL PHP CONSEGUIDA! 🖨️🚀 >> MANUAL_FORMATO_PHP.txt

        REM Probador formato PHP
        echo @echo off > PROBAR_FORMATO_PHP.bat
        echo echo 🚀 Probando GridPos Print Service - Formato PHP... >> PROBAR_FORMATO_PHP.bat
        echo echo. >> PROBAR_FORMATO_PHP.bat
        echo echo ✅ Formato IDÉNTICO al PrinterController.php >> PROBAR_FORMATO_PHP.bat
        echo echo 📏 Soporte papel 58mm y 80mm dinámico >> PROBAR_FORMATO_PHP.bat
        echo echo 🛒 Productos con formato específico >> PROBAR_FORMATO_PHP.bat
        echo echo 📝 WordWrap notas optimizado >> PROBAR_FORMATO_PHP.bat
        echo echo 💰 Apertura caja integrada >> PROBAR_FORMATO_PHP.bat
        echo echo 📋 Logs detallados del proceso >> PROBAR_FORMATO_PHP.bat
        echo echo. >> PROBAR_FORMATO_PHP.bat
        echo echo 🔧 CONFIGURAR: >> PROBAR_FORMATO_PHP.bat
        echo echo   1. Conectar impresora térmica >> PROBAR_FORMATO_PHP.bat
        echo echo   2. Configurar API + Client Slug >> PROBAR_FORMATO_PHP.bat
        echo echo   3. Iniciar servicio >> PROBAR_FORMATO_PHP.bat
        echo echo   4. Enviar orden con data_json desde web >> PROBAR_FORMATO_PHP.bat
        echo echo   5. Verificar formato idéntico al PHP >> PROBAR_FORMATO_PHP.bat
        echo echo. >> PROBAR_FORMATO_PHP.bat
        echo GridPosPrintService.exe >> PROBAR_FORMATO_PHP.bat

        echo    ✅ MANUAL_FORMATO_PHP.txt - Equivalencias técnicas PHP^<-^>C#
        echo    ✅ PROBAR_FORMATO_PHP.bat - Prueba formato idéntico
        echo.
        echo ========================================
        echo      🎉 ¡FORMATO PHP REPLICADO 100%%!
        echo ========================================
        echo.
        echo 🖨️ EQUIVALENCIA PERFECTA CONSEGUIDA:
        echo    📋 printOrderWithEscPos() replicado
        echo    📏 Papel 58mm/80mm dinámico
        echo    🛒 Productos formato específico
        echo    📝 WordWrap notas optimizado
        echo    💰 Apertura caja integrada
        echo    ✂️ Corte papel automático
        echo.
        echo 🔧 FUNCIONALIDADES IDÉNTICAS:
        echo    🏷️ Cliente centrado adaptativo
        echo    📅 Fecha + teléfono + dirección
        echo    ➖ Separadores dinámicos (32/48 chars)
        echo    📝 Headers columnas específicos
        echo    🔢 ID orden con lógica shipping_address
        echo    👤 Usuario + timestamp impresión
        echo.
        echo 📋 LOGS ESPECÍFICOS:
        echo    "📝 Generando ticket ESC/POS IGUAL AL PHP..."
        echo    "🚀 Ancho de papel: {paperWidth}"
        echo    "🚀 Orden impresa con ESC/POS en {ms}ms"
        echo    "💰 Caja abierta como parte del proceso"
        echo.
        echo 📦 ARCHIVOS FINALES:
        echo    🚀 GridPosPrintService.exe ^(!FILE_SIZE_MB! MB^)
        echo    📖 MANUAL_FORMATO_PHP.txt
        echo    🧪 PROBAR_FORMATO_PHP.bat
        echo.
        echo 🎯 RESULTADO:
        echo    ✅ Tickets IDÉNTICOS al sistema PHP
        echo    ✅ Misma calidad de impresión
        echo    ✅ Mismo comportamiento dinámico
        echo    ✅ Compatible 100%% con API actual
        echo.
        echo 🎉 ¡SISTEMA NATIVO C# CON FORMATO PHP!
        echo    ⭐ Sin dependencias PHP/VBS
        echo    ⭐ Impresión física directa
        echo    ⭐ Logs visuales tiempo real
        echo    ⭐ Configuración súper simple
        echo    ⭐ Windows 10/11 optimizado
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
echo 🎉 COMPILADOR FORMATO PHP - GridPos Print Service
echo 📧 Soporte técnico: soporte@gridpos.com
echo 🖨️ Formato idéntico al PrinterController.php
echo.
pause
