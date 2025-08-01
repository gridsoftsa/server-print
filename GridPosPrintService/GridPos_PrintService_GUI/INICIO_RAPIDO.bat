@echo off
chcp 65001 >nul
echo ========================================
echo    GRIDPOS PRINT SERVICE - INICIO RÁPIDO
echo      🚀 Sistema Completo Windows
echo ========================================
echo.

echo 🎯 BIENVENIDO AL SISTEMA GRIDPOS PRINT SERVICE
echo =============================================
echo.
echo 📦 SISTEMA COMPLETO INCLUIDO:
echo ✅ GUI Moderna Windows Forms
echo ✅ Impresión ESC/POS directa
echo ✅ Formato idéntico al PHP
echo ✅ Configuración dinámica
echo ✅ Logs tiempo real
echo ✅ Auto-inicio Windows
echo.

echo 🔧 ARCHIVOS DISPONIBLES EN ESTA CARPETA:
echo =========================================
echo.

if exist "MainForm.cs" (
    echo ✅ MainForm.cs - Código principal GUI
) else (
    echo ❌ MainForm.cs - FALTANTE
)

if exist "GridPosPrintService.csproj" (
    echo ✅ GridPosPrintService.csproj - Configuración proyecto
) else (
    echo ❌ GridPosPrintService.csproj - FALTANTE
)

if exist "Program.cs" (
    echo ✅ Program.cs - Punto de entrada
) else (
    echo ✅ Program.cs - Se creará automáticamente
)

echo.
echo 🚀 SCRIPTS DE UTILIDAD:
echo =======================
if exist "COMPILADOR_FINAL.bat" (
    echo ✅ COMPILADOR_FINAL.bat - Compilador principal unificado
) else (
    echo ❌ COMPILADOR_FINAL.bat - FALTANTE
)

if exist "VERIFICAR_SISTEMA.bat" (
    echo ✅ VERIFICAR_SISTEMA.bat - Diagnóstico completo sistema
) else (
    echo ❌ VERIFICAR_SISTEMA.bat - FALTANTE
)

if exist "INSTALAR_DOTNET.bat" (
    echo ✅ INSTALAR_DOTNET.bat - Instalador automático .NET 6
) else (
    echo ❌ INSTALAR_DOTNET.bat - FALTANTE
)

if exist "README_COMPILACION.md" (
    echo ✅ README_COMPILACION.md - Guía completa
) else (
    echo ❌ README_COMPILACION.md - FALTANTE
)

echo.
echo 🎯 ¿QUÉ QUIERES HACER?
echo =====================
echo.
echo 1️⃣ 🚀 COMPILAR INMEDIATAMENTE
echo    📁 Ejecutar: COMPILADOR_FINAL.bat
echo    ⚡ Compila directamente si tienes .NET 6 SDK
echo.
echo 2️⃣ 🔍 VERIFICAR SISTEMA PRIMERO
echo    📁 Ejecutar: VERIFICAR_SISTEMA.bat
echo    🔍 Diagnóstico completo antes de compilar
echo.
echo 3️⃣ 📥 INSTALAR .NET 6 SDK
echo    📁 Ejecutar: INSTALAR_DOTNET.bat
echo    🔧 Descarga e instala .NET automáticamente
echo.
echo 4️⃣ 📖 LEER DOCUMENTACIÓN
echo    📄 Abrir: README_COMPILACION.md
echo    📋 Guía completa paso a paso
echo.

echo 💡 RECOMENDACIÓN PARA PRINCIPIANTES:
echo ===================================
echo 1. Ejecutar VERIFICAR_SISTEMA.bat primero
echo 2. Si .NET falta, ejecutar INSTALAR_DOTNET.bat
echo 3. Luego ejecutar COMPILADOR_FINAL.bat
echo 4. ¡Listo para usar!
echo.

echo 🔧 RECOMENDACIÓN PARA EXPERTOS:
echo ==============================
echo 1. Ejecutar directamente COMPILADOR_FINAL.bat
echo 2. El script detecta y soluciona problemas automáticamente
echo.

echo ⚠️ IMPORTANTE:
echo ==============
echo ✅ Ejecutar como Administrador
echo ✅ Tener conexión a internet
echo ✅ Antivirus desactivado temporalmente
echo.

:menu
echo 📝 Selecciona una opción:
echo.
echo [1] 🚀 Compilar ahora (COMPILADOR_FINAL.bat)
echo [2] 🔍 Verificar sistema (VERIFICAR_SISTEMA.bat)
echo [3] 📥 Instalar .NET (INSTALAR_DOTNET.bat)
echo [4] 📖 Leer guía (README_COMPILACION.md)
echo [5] ❌ Salir
echo.
set /p "choice=Ingresa tu opción (1-5): "

if "%choice%"=="1" (
    echo.
    echo 🚀 Ejecutando COMPILADOR_FINAL.bat...
    if exist "COMPILADOR_FINAL.bat" (
        call "COMPILADOR_FINAL.bat"
    ) else (
        echo ❌ ERROR: COMPILADOR_FINAL.bat no encontrado
        pause
    )
    goto end
)

if "%choice%"=="2" (
    echo.
    echo 🔍 Ejecutando VERIFICAR_SISTEMA.bat...
    if exist "VERIFICAR_SISTEMA.bat" (
        call "VERIFICAR_SISTEMA.bat"
    ) else (
        echo ❌ ERROR: VERIFICAR_SISTEMA.bat no encontrado
        pause
    )
    goto menu
)

if "%choice%"=="3" (
    echo.
    echo 📥 Ejecutando INSTALAR_DOTNET.bat...
    if exist "INSTALAR_DOTNET.bat" (
        call "INSTALAR_DOTNET.bat"
    ) else (
        echo ❌ ERROR: INSTALAR_DOTNET.bat no encontrado
        pause
    )
    goto menu
)

if "%choice%"=="4" (
    echo.
    echo 📖 Abriendo README_COMPILACION.md...
    if exist "README_COMPILACION.md" (
        start "" "README_COMPILACION.md"
    ) else (
        echo ❌ ERROR: README_COMPILACION.md no encontrado
        pause
    )
    goto menu
)

if "%choice%"=="5" (
    goto end
)

echo ❌ Opción inválida. Intenta de nuevo.
goto menu

:end
echo.
echo 🎉 GRIDPOS PRINT SERVICE
echo =======================
echo 📧 Soporte: soporte@gridpos.com
echo 🌐 Web: https://gridpos.com
echo.
echo ✅ ¡Gracias por usar GridPos Print Service!
echo.
pause
