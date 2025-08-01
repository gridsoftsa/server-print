@echo off
chcp 65001 >nul
echo ========================================
echo     COMPILAR GRIDPOS EN WINDOWS
echo       Programa con Interfaz Gráfica
echo ========================================
echo.

echo 🎯 OBJETIVO: Crear programa .exe con ventanas y botones
echo.

REM Verificar .NET
echo 🔍 Verificando .NET SDK...
dotnet --version >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ ERROR: .NET SDK no está instalado
    echo.
    echo 📥 INSTALAR .NET 6:
    echo    1. Ir a: https://dotnet.microsoft.com/download/dotnet/6.0
    echo    2. Descargar "SDK x64" para Windows
    echo    3. Instalar y reiniciar
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('dotnet --version') do set DOTNET_VERSION=%%i
echo ✅ .NET SDK detectado: %DOTNET_VERSION%
echo.

REM Limpiar compilaciones anteriores
echo 🧹 Limpiando compilaciones anteriores...
if exist bin rmdir /s /q bin
if exist obj rmdir /s /q obj
if exist GridPosPrintService.exe del GridPosPrintService.exe
echo ✅ Limpieza completada
echo.

REM Compilar
echo 🔨 Compilando programa con interfaz gráfica...
echo    - Target: Windows 10/11 x64
echo    - Interfaz: Windows Forms (ventanas y botones)
echo    - Tamaño: Optimizado
echo    - Dependencias: Incluidas
echo.

dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o .

if %errorLevel% equ 0 (
    echo.
    echo ✅ COMPILACIÓN EXITOSA
    echo.

    if exist GridPosPrintService.exe (
        for %%A in (GridPosPrintService.exe) do set FILE_SIZE=%%~zA
        set /a FILE_SIZE_MB=%FILE_SIZE% / 1024 / 1024
        echo 🚀 Programa creado: GridPosPrintService.exe (!FILE_SIZE_MB! MB)
        echo.

        echo 📦 CREANDO ARCHIVOS DE AYUDA...

        REM Manual de uso
        echo GRIDPOS PRINT SERVICE - CON INTERFAZ GRÁFICA > COMO_USAR.txt
        echo =========================================== >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo 📦 ARCHIVO PRINCIPAL: >> COMO_USAR.txt
        echo - GridPosPrintService.exe  ← EL PROGRAMA >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo 🚀 COMO USAR: >> COMO_USAR.txt
        echo ============ >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 1: EJECUTAR >> COMO_USAR.txt
        echo - Doble clic en "GridPosPrintService.exe" >> COMO_USAR.txt
        echo - Se abre una ventana bonita con botones >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 2: CONFIGURAR >> COMO_USAR.txt
        echo - Selecciona API (Producción o Demo) >> COMO_USAR.txt
        echo - Escribe tu Client Slug >> COMO_USAR.txt
        echo - Clic en "💾 Guardar Configuración" >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 3: INICIAR >> COMO_USAR.txt
        echo - Clic en "▶️ INICIAR SERVICIO" >> COMO_USAR.txt
        echo - ¡Ya está funcionando! >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo ✅ CARACTERÍSTICAS: >> COMO_USAR.txt
        echo - 🖼️ Interfaz gráfica amigable >> COMO_USAR.txt
        echo - 🔧 Configuración super fácil >> COMO_USAR.txt
        echo - 📊 Estado en tiempo real >> COMO_USAR.txt
        echo - ⚡ Monitoreo cada 2 segundos >> COMO_USAR.txt
        echo - 💾 Guarda configuración automáticamente >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo ¡SÚPER FÁCIL DE USAR! 🎉 >> COMO_USAR.txt

        REM Instalador fácil
        echo @echo off > INSTALAR_FACIL.bat
        echo chcp 65001 ^>nul >> INSTALAR_FACIL.bat
        echo echo ======================================== >> INSTALAR_FACIL.bat
        echo echo      GRIDPOS PRINT SERVICE GUI >> INSTALAR_FACIL.bat
        echo echo        INSTALADOR SUPER FACIL >> INSTALAR_FACIL.bat
        echo echo ======================================== >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo 🎯 INSTALACIÓN AUTOMÁTICA >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo set INSTALL_DIR=C:\GridPos >> INSTALAR_FACIL.bat
        echo echo 📁 Creando carpeta: %%INSTALL_DIR%% >> INSTALAR_FACIL.bat
        echo mkdir "%%INSTALL_DIR%%" 2^>nul >> INSTALAR_FACIL.bat
        echo echo 📦 Copiando programa... >> INSTALAR_FACIL.bat
        echo copy "GridPosPrintService.exe" "%%INSTALL_DIR%%\" ^>nul >> INSTALAR_FACIL.bat
        echo copy "COMO_USAR.txt" "%%INSTALL_DIR%%\" ^>nul >> INSTALAR_FACIL.bat
        echo echo 🖥️ Creando acceso directo en escritorio... >> INSTALAR_FACIL.bat
        echo echo Set oWS = WScript.CreateObject("WScript.Shell"^) ^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo sLinkFile = "%%USERPROFILE%%\Desktop\GridPos Print Service.lnk" ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo Set oLink = oWS.CreateShortcut(sLinkFile^) ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo oLink.TargetPath = "%%INSTALL_DIR%%\GridPosPrintService.exe" ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo oLink.WorkingDirectory = "%%INSTALL_DIR%%" ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo oLink.Description = "GridPos Print Service" ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo echo oLink.Save ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
        echo cscript CreateShortcut.vbs ^>nul 2^>^&1 >> INSTALAR_FACIL.bat
        echo del CreateShortcut.vbs ^>nul 2^>^&1 >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo ✅ INSTALACIÓN COMPLETADA >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo 📍 Programa instalado en: %%INSTALL_DIR%% >> INSTALAR_FACIL.bat
        echo echo 🖥️ Acceso directo creado en escritorio >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo 🚀 PARA USAR: >> INSTALAR_FACIL.bat
        echo echo     1. Doble clic en el icono del escritorio >> INSTALAR_FACIL.bat
        echo echo     2. Configurar API y Client Slug >> INSTALAR_FACIL.bat
        echo echo     3. Dar clic en INICIAR SERVICIO >> INSTALAR_FACIL.bat
        echo echo     4. ¡Listo! >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo 💡 El programa tiene interfaz gráfica súper fácil >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo pause >> INSTALAR_FACIL.bat

        echo    ✅ COMO_USAR.txt - Manual simple
        echo    ✅ INSTALAR_FACIL.bat - Instalador con acceso directo
        echo.
        echo ========================================
        echo        🎉 PROGRAMA LISTO PARA USAR
        echo ========================================
        echo.
        echo 🖼️ CARACTERÍSTICAS:
        echo    ✅ Interfaz gráfica Windows
        echo    ✅ Botones grandes y claros
        echo    ✅ Configuración visual
        echo    ✅ Estado en tiempo real
        echo    ✅ Un solo archivo .exe
        echo.
        echo 📦 ARCHIVOS PARA ENTREGAR:
        echo    🚀 GridPosPrintService.exe
        echo    📖 COMO_USAR.txt
        echo    ⚙️ INSTALAR_FACIL.bat
        echo.
        echo 🎯 USO SÚPER SIMPLE:
        echo    1. Cliente ejecuta INSTALAR_FACIL.bat
        echo    2. Usa el icono del escritorio
        echo    3. Configura en ventanas
        echo    4. ¡Funciona!
        echo.
        echo 🎉 ¡TU PROGRAMA ESTÁ LISTO!
        echo.

    ) else (
        echo ❌ ERROR: No se generó el ejecutable
        echo    Revisa los errores mostrados arriba
    )
) else (
    echo.
    echo ❌ ERROR EN LA COMPILACIÓN
    echo    Revisa los errores mostrados arriba
    echo.
)

echo.
pause
