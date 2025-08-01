@echo off
chcp 65001 >nul
echo ========================================
echo    VERIFICADOR SISTEMA GRIDPOS
echo      🔍 Diagnóstico Completo
echo ========================================
echo.

echo 🔧 DIAGNÓSTICO DEL SISTEMA:
echo ===========================

REM Verificar Windows
echo 🪟 Sistema Operativo:
ver
echo.

REM Verificar .NET SDK
echo 🔍 Verificando .NET SDK...
set DOTNET_FOUND=0

if exist "C:\Program Files\dotnet\dotnet.exe" (
    echo ✅ .NET encontrado en: C:\Program Files\dotnet\dotnet.exe
    "C:\Program Files\dotnet\dotnet.exe" --version 2>nul && set DOTNET_FOUND=1
) else if exist "C:\Program Files (x86)\dotnet\dotnet.exe" (
    echo ✅ .NET encontrado en: C:\Program Files (x86)\dotnet\dotnet.exe
    "C:\Program Files (x86)\dotnet\dotnet.exe" --version 2>nul && set DOTNET_FOUND=1
) else (
    where dotnet >nul 2>&1
    if %errorlevel% equ 0 (
        echo ✅ .NET encontrado en PATH
        dotnet --version 2>nul && set DOTNET_FOUND=1
    ) else (
        echo ❌ .NET SDK no encontrado
    )
)

if %DOTNET_FOUND%==1 (
    echo ✅ .NET SDK está funcionando
) else (
    echo ❌ .NET SDK no funciona correctamente
)
echo.

REM Verificar archivos del proyecto
echo 📂 Verificando archivos del proyecto:
echo ====================================

if exist "MainForm.cs" (
    for %%A in (MainForm.cs) do set MAINFORM_SIZE=%%~zA
    echo ✅ MainForm.cs - Tamaño: !MAINFORM_SIZE! bytes
) else (
    echo ❌ MainForm.cs - FALTANTE
)

if exist "GridPosPrintService.csproj" (
    echo ✅ GridPosPrintService.csproj - Encontrado
) else (
    echo ❌ GridPosPrintService.csproj - FALTANTE
)

if exist "Program.cs" (
    echo ✅ Program.cs - Encontrado
) else (
    echo ⚠️ Program.cs - FALTANTE (se puede crear automáticamente)
)

if exist "COMPILADOR_FINAL.bat" (
    echo ✅ COMPILADOR_FINAL.bat - Encontrado
) else (
    echo ❌ COMPILADOR_FINAL.bat - FALTANTE
)
echo.

REM Verificar conectividad
echo 🌐 Verificando conectividad a NuGet:
echo ===================================
ping api.nuget.org -n 1 >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Conectividad a NuGet OK
) else (
    echo ❌ Sin conectividad a NuGet (verificar firewall/proxy)
)
echo.

REM Verificar permisos
echo 🔐 Verificando permisos:
echo =======================
echo %CD% | findstr /C:"Program Files" >nul
if %errorlevel% equ 0 (
    echo ⚠️ Directorio en Program Files - se requiere Administrador
) else (
    echo ✅ Directorio con permisos de escritura
)

REM Verificar si corremos como Admin
net session >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Ejecutándose como Administrador
) else (
    echo ⚠️ NO ejecutándose como Administrador
)
echo.

REM Verificar espacio en disco
echo 💾 Verificando espacio en disco:
echo ===============================
for /f "tokens=3" %%A in ('dir /-c %SystemDrive%\ 2^>nul ^| find "bytes free"') do set FREE_SPACE=%%A
if defined FREE_SPACE (
    echo ✅ Espacio libre disponible en %SystemDrive%\
) else (
    echo ⚠️ No se pudo verificar espacio en disco
)
echo.

echo 📋 RESUMEN DEL DIAGNÓSTICO:
echo ===========================
if %DOTNET_FOUND%==1 (
    echo ✅ .NET SDK instalado y funcionando
) else (
    echo ❌ PROBLEMA: .NET SDK no disponible
    echo 📥 SOLUCIÓN: Instalar .NET 6 SDK desde https://dotnet.microsoft.com/download/dotnet/6.0
)

if exist "MainForm.cs" if exist "GridPosPrintService.csproj" (
    echo ✅ Archivos principales del proyecto presentes
) else (
    echo ❌ PROBLEMA: Archivos principales faltantes
    echo 📥 SOLUCIÓN: Verificar que todos los archivos estén en la carpeta
)

echo.
echo 🚀 RECOMENDACIONES:
echo ==================
if %DOTNET_FOUND%==0 (
    echo 1. 📥 Instalar .NET 6 SDK primero
)
echo 2. 🔧 Ejecutar como Administrador
echo 3. 🌐 Verificar conexión a internet
echo 4. 🚀 Ejecutar COMPILADOR_FINAL.bat
echo.

echo 📞 Si persisten problemas:
echo 📧 soporte@gridpos.com
echo 🌐 https://gridpos.com/soporte
echo.
pause
