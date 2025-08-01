@echo off
chcp 65001 >nul
echo ========================================
echo     COMPILAR GRIDPOS - VERSION CORREGIDA
echo       Programa con Interfaz Gráfica
========================================
echo.

echo 🎯 OBJETIVO: Crear programa .exe con ventanas y botones
echo    ✅ Error de Timer ya CORREGIDO
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
if exist GridPosPrintService.pdb del GridPosPrintService.pdb
echo ✅ Limpieza completada
echo.

REM Compilar
echo 🔨 Compilando programa con interfaz gráfica...
echo    - Target: Windows 10/11 x64
echo    - Interfaz: Windows Forms (ventanas y botones)
echo    - Tamaño: Optimizado
echo    - Dependencias: Incluidas
echo    - Timer: CORREGIDO ✅
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
        echo 🚀 COMO USAR EL PROGRAMA: >> COMO_USAR.txt
        echo ========================= >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 1: EJECUTAR >> COMO_USAR.txt
        echo - Doble clic en "GridPosPrintService.exe" >> COMO_USAR.txt
        echo - Se abre una ventana bonita con botones >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 2: CONFIGURAR >> COMO_USAR.txt
        echo - Arriba: Selecciona API (Producción o Demo) >> COMO_USAR.txt
        echo - Abajo: Escribe tu Client Slug >> COMO_USAR.txt
        echo - Clic en "💾 Guardar Configuración" >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo PASO 3: INICIAR >> COMO_USAR.txt
        echo - Clic en "▶️ INICIAR SERVICIO" >> COMO_USAR.txt
        echo - El estado cambia a verde >> COMO_USAR.txt
        echo - ¡Ya está funcionando! >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo COLORES DEL PROGRAMA: >> COMO_USAR.txt
        echo - 🟢 Verde = Todo funcionando bien >> COMO_USAR.txt
        echo - 🔴 Rojo = Detenido o error >> COMO_USAR.txt
        echo - 🔵 Azul = Encontró trabajos para imprimir >> COMO_USAR.txt
        echo. >> COMO_USAR.txt
        echo BOTONES DISPONIBLES: >> COMO_USAR.txt
        echo - ▶️ INICIAR SERVICIO = Empezar a monitorear >> COMO_USAR.txt
        echo - ⏹️ DETENER SERVICIO = Parar monitoreo >> COMO_USAR.txt
        echo - ❓ AYUDA = Ver información del programa >> COMO_USAR.txt
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
        echo echo    ✅ Programa con ventanas y botones >> INSTALAR_FACIL.bat
        echo echo    ✅ Configuración súper fácil >> INSTALAR_FACIL.bat
        echo echo    ✅ Icono en el escritorio >> INSTALAR_FACIL.bat
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
        echo echo oLink.Description = "GridPos Print Service - Sistema Ultra Rapido" ^>^> CreateShortcut.vbs >> INSTALAR_FACIL.bat
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
        echo echo     2. Seleccionar API y escribir Client Slug >> INSTALAR_FACIL.bat
        echo echo     3. Clic en "Guardar Configuración" >> INSTALAR_FACIL.bat
        echo echo     4. Clic en "INICIAR SERVICIO" >> INSTALAR_FACIL.bat
        echo echo     5. ¡Listo! >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo echo 💡 El programa tiene interfaz gráfica súper fácil >> INSTALAR_FACIL.bat
        echo echo    🟢 Verde = Funcionando   🔴 Rojo = Detenido >> INSTALAR_FACIL.bat
        echo echo. >> INSTALAR_FACIL.bat
        echo pause >> INSTALAR_FACIL.bat

        REM Prueba rápida
        echo @echo off > PROBAR_PROGRAMA.bat
        echo echo 🚀 Probando GridPos Print Service... >> PROBAR_PROGRAMA.bat
        echo echo. >> PROBAR_PROGRAMA.bat
        echo echo ✅ Si se abre una ventana con botones = FUNCIONA >> PROBAR_PROGRAMA.bat
        echo echo ❌ Si aparece error = Reportar el problema >> PROBAR_PROGRAMA.bat
        echo echo. >> PROBAR_PROGRAMA.bat
        echo GridPosPrintService.exe >> PROBAR_PROGRAMA.bat

        echo    ✅ COMO_USAR.txt - Manual detallado
        echo    ✅ INSTALAR_FACIL.bat - Instalador con acceso directo
        echo    ✅ PROBAR_PROGRAMA.bat - Prueba rápida
        echo.
        echo ========================================
        echo        🎉 PROGRAMA LISTO PARA USAR
        echo ========================================
        echo.
        echo 🖼️ CARACTERÍSTICAS:
        echo    ✅ Interfaz gráfica Windows
        echo    ✅ Botones grandes y claros
        echo    ✅ Configuración visual en 2 campos
        echo    ✅ Estado en tiempo real con colores
        echo    ✅ Un solo archivo .exe
        echo    ✅ Error de Timer CORREGIDO
        echo.
        echo 📦 ARCHIVOS PARA ENTREGAR A CLIENTES:
        echo    🚀 GridPosPrintService.exe - Programa principal
        echo    📖 COMO_USAR.txt - Manual simple
        echo    ⚙️ INSTALAR_FACIL.bat - Instalador automático
        echo    🧪 PROBAR_PROGRAMA.bat - Para probar
        echo.
        echo 🎯 USO SÚPER SIMPLE PARA EL CLIENTE:
        echo    1. Ejecutar INSTALAR_FACIL.bat
        echo    2. Usar el icono del escritorio
        echo    3. Configurar API y Client Slug
        echo    4. Iniciar servicio
        echo    5. ¡Funciona automáticamente!
        echo.
        echo 🎉 ¡TU PROGRAMA CON INTERFAZ ESTÁ LISTO!
        echo    Error corregido - Debería compilar perfectamente
        echo.

    ) else (
        echo ❌ ERROR: No se generó el ejecutable
        echo    Revisa los errores mostrados arriba
    )
) else (
    echo.
    echo ❌ ERROR EN LA COMPILACIÓN
    echo    Si aparece error de Timer, usar COMPILAR_ARREGLADO.bat
    echo    Revisa los errores mostrados arriba
    echo.
)

echo.
echo 💡 Si necesitas ayuda: soporte@gridpos.com
echo.
pause
