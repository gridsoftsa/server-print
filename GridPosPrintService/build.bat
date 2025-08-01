@echo off
echo ========================================
echo     COMPILADOR GRIDPOS PRINT SERVICE
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

echo ✓ .NET SDK detectado
dotnet --version
echo.

REM Limpiar compilaciones anteriores
echo 🧹 Limpiando compilaciones anteriores...
if exist "bin" rmdir /s /q "bin" 2>nul
if exist "obj" rmdir /s /q "obj" 2>nul
echo ✅ Limpieza completada
echo.

REM Compilar para Windows 10/11 x64
echo 🔨 Compilando para Windows 10/11 x64...
echo.

dotnet publish -c Release -r win10-x64 --self-contained true -p:PublishSingleFile=true -p:PublishTrimmed=true

if %errorLevel% equ 0 (
    echo.
    echo ✅ Compilación exitosa
    echo.
    echo 📁 Archivos generados en:
    echo    bin\Release\net6.0-windows\win10-x64\publish\
    echo.
    echo 🚀 Archivos principales:
    echo    ✓ GridPosPrintService.exe
    echo    ✓ appsettings.json
    echo    ✓ install.bat
    echo    ✓ uninstall.bat
    echo.
    echo ========================================
    echo       COMPILACIÓN COMPLETADA
    echo ========================================
    echo.
    echo Para instalar el servicio:
    echo   1. Copia todos los archivos a la máquina destino
    echo   2. Ejecuta install.bat como administrador
    echo.
    echo ¿Quieres abrir la carpeta de archivos compilados? (S/N)
    set /p OPEN_FOLDER=
    if /i "%OPEN_FOLDER%"=="S" (
        explorer "bin\Release\net6.0-windows\win10-x64\publish\"
    )
) else (
    echo.
    echo ❌ Error en la compilación
    echo Revisa los errores mostrados arriba
)

echo.
pause
