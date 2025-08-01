@echo off
echo ========================================
echo     COMPILADOR CROSS-PLATFORM
echo     GridPos Print Service
echo ========================================
echo.

REM Verificar que .NET 6 esté instalado
dotnet --version >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: .NET 6 SDK no está instalado
    echo.
    echo Descarga e instala .NET 6 SDK desde:
    echo https://dotnet.microsoft.com/download/dotnet/6.0
    echo.
    pause
    exit /b 1
)

echo ✅ .NET SDK detectado
dotnet --version
echo.

REM Limpiar compilaciones anteriores
echo 🧹 Limpiando compilaciones anteriores...
if exist "bin" rmdir /s /q "bin" 2>nul
if exist "obj" rmdir /s /q "obj" 2>nul
echo ✅ Limpieza completada
echo.

REM Crear proyecto temporal para compilación cross-platform
echo 🔄 Creando configuración temporal para compilación...
copy GridPosPrintService.csproj GridPosPrintService.csproj.backup >nul

REM Modificar temporalmente el target framework
powershell -Command "(Get-Content GridPosPrintService.csproj) -replace 'net6.0-windows', 'net6.0' | Set-Content GridPosPrintService.csproj"
powershell -Command "(Get-Content GridPosPrintService.csproj) -replace '<UseWindowsForms>true</UseWindowsForms>', '' | Set-Content GridPosPrintService.csproj"

REM Compilar para Windows x64
echo 🔨 Compilando para Windows x64...
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:PublishTrimmed=false

REM Restaurar archivo original
move GridPosPrintService.csproj.backup GridPosPrintService.csproj >nul

if %errorLevel% equ 0 (
    echo.
    echo ✅ Compilación exitosa
    echo.
    echo 📁 Archivos generados en:
    echo    bin\Release\net6.0\win-x64\publish\
    echo.
    echo 🚀 Archivos principales:
    echo    ✓ GridPosPrintService.exe
    echo    ✓ appsettings.json
    echo    ✓ install_interactive.bat
    echo    ✓ check_config.bat
    echo    ✓ uninstall.bat
    echo.

    REM Copiar archivos auxiliares
    echo 📦 Copiando archivos auxiliares...
    copy appsettings.json "bin\Release\net6.0\win-x64\publish\" >nul
    copy install_interactive.bat "bin\Release\net6.0\win-x64\publish\" >nul
    copy check_config.bat "bin\Release\net6.0\win-x64\publish\" >nul
    copy uninstall.bat "bin\Release\net6.0\win-x64\publish\" >nul
    copy README.md "bin\Release\net6.0\win-x64\publish\" >nul

    echo ✅ Archivos auxiliares copiados
    echo.
    echo ========================================
    echo       COMPILACIÓN COMPLETADA
    echo ========================================
    echo.
    echo Para usar en Windows:
    echo   1. Copia la carpeta "bin\Release\net6.0\win-x64\publish" a Windows
    echo   2. Ejecuta "install_interactive.bat" como administrador
    echo.
    echo ¿Quieres abrir la carpeta de archivos compilados? (S/N)
    set /p OPEN_FOLDER=
    if /i "%OPEN_FOLDER%"=="S" (
        explorer "bin\Release\net6.0\win-x64\publish\"
    )
) else (
    echo.
    echo ❌ Error en la compilación
    echo Revisa los errores mostrados arriba
)

echo.
pause
