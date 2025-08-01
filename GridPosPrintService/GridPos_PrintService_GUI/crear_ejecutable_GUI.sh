#!/bin/bash

echo "========================================"
echo "  GRIDPOS PRINT SERVICE CON INTERFAZ"
echo "    Programa Ultra Simple para Windows"
echo "========================================"
echo

echo "🎯 CREANDO: Programa con ventanas y botones"
echo "   ✅ Interfaz gráfica super fácil"
echo "   ✅ Botones grandes y claros"
echo "   ✅ Configuración visual"
echo "   ✅ Un solo .exe"
echo

# Verificar .NET
if ! command -v dotnet &> /dev/null; then
    echo "❌ ERROR: .NET SDK no está instalado"
    exit 1
fi

echo "✅ .NET SDK: $(dotnet --version)"
echo

# Limpiar
echo "🧹 Limpiando compilaciones anteriores..."
rm -rf bin obj 2>/dev/null
echo "✅ Limpio"
echo

# Compilar
echo "🔨 Compilando programa con interfaz..."
echo "   - Target: Windows 10/11"
echo "   - Interfaz: Windows Forms"
echo "   - Tamaño: Optimizado"
echo

dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o .

if [ $? -eq 0 ]; then
    echo
    echo "✅ COMPILACIÓN EXITOSA"
    echo

    if [ -f "GridPosPrintService.exe" ]; then
        size=$(du -h "GridPosPrintService.exe" | cut -f1)
        echo "🚀 Programa creado: GridPosPrintService.exe ($size)"

        # Crear archivos de ayuda
        cat > "COMO_USAR.txt" << 'EOF'
GRIDPOS PRINT SERVICE - CON INTERFAZ GRÁFICA
===========================================

📦 CONTENIDO:
- GridPosPrintService.exe  ← EL PROGRAMA PRINCIPAL

🚀 COMO USAR:
============

PASO 1: EJECUTAR
- Doble clic en "GridPosPrintService.exe"
- Se abre una ventana bonita

PASO 2: CONFIGURAR
- Selecciona API (Producción o Demo)
- Escribe tu Client Slug
- Clic en "💾 Guardar Configuración"

PASO 3: INICIAR
- Clic en "▶️ INICIAR SERVICIO"
- ¡Ya está funcionando!

✅ CARACTERÍSTICAS:
- 🖼️ Interfaz gráfica amigable
- 🔧 Configuración super fácil
- 📊 Estado en tiempo real
- ⚡ Monitoreo cada 2 segundos
- 💾 Guarda configuración automáticamente

🔄 FUNCIONAMIENTO:
- Verde = Todo bien
- Rojo = Error o detenido
- Azul = Encontró trabajos para imprimir

❓ AYUDA:
- Clic en "❓ AYUDA" en el programa
- Email: soporte@gridpos.com

¡SÚPER FÁCIL DE USAR! 🎉
EOF

        cat > "INSTALAR_FACIL.bat" << 'EOF'
@echo off
chcp 65001 >nul
echo ========================================
echo     GRIDPOS PRINT SERVICE GUI
echo       INSTALADOR SUPER FACIL
echo ========================================
echo.

echo 🎯 INSTALACIÓN AUTOMÁTICA
echo.

REM Crear directorio
set INSTALL_DIR=C:\GridPos
echo 📁 Creando carpeta: %INSTALL_DIR%
mkdir "%INSTALL_DIR%" 2>nul

REM Copiar programa
echo 📦 Copiando programa...
copy "GridPosPrintService.exe" "%INSTALL_DIR%\" >nul
copy "COMO_USAR.txt" "%INSTALL_DIR%\" >nul

REM Crear acceso directo en escritorio
echo 🖥️ Creando acceso directo en escritorio...

echo Set oWS = WScript.CreateObject("WScript.Shell") > CreateShortcut.vbs
echo sLinkFile = "%USERPROFILE%\Desktop\GridPos Print Service.lnk" >> CreateShortcut.vbs
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> CreateShortcut.vbs
echo oLink.TargetPath = "%INSTALL_DIR%\GridPosPrintService.exe" >> CreateShortcut.vbs
echo oLink.WorkingDirectory = "%INSTALL_DIR%" >> CreateShortcut.vbs
echo oLink.Description = "GridPos Print Service" >> CreateShortcut.vbs
echo oLink.Save >> CreateShortcut.vbs

cscript CreateShortcut.vbs >nul 2>&1
del CreateShortcut.vbs >nul 2>&1

echo.
echo ✅ INSTALACIÓN COMPLETADA
echo.
echo 📍 Programa instalado en: %INSTALL_DIR%
echo 🖥️ Acceso directo creado en escritorio
echo.
echo 🚀 PARA USAR:
echo    1. Doble clic en el icono del escritorio
echo    2. Configurar API y Client Slug
echo    3. Dar clic en INICIAR SERVICIO
echo    4. ¡Listo!
echo.
echo 💡 El programa tiene interfaz gráfica súper fácil
echo.
pause
EOF

        echo
        echo "   ✅ COMO_USAR.txt - Manual simple"
        echo "   ✅ INSTALAR_FACIL.bat - Instalador con acceso directo"
        echo
        echo "========================================"
        echo "   🎉 PROGRAMA CON INTERFAZ LISTO"
        echo "========================================"
        echo
        echo "🖼️ CARACTERÍSTICAS:"
        echo "   ✅ Interfaz gráfica Windows"
        echo "   ✅ Botones grandes y claros"
        echo "   ✅ Configuración visual"
        echo "   ✅ Estado en tiempo real"
        echo "   ✅ Un solo archivo .exe"
        echo
        echo "📦 PARA ENTREGAR:"
        echo "   🚀 GridPosPrintService.exe"
        echo "   📖 COMO_USAR.txt"
        echo "   ⚙️ INSTALAR_FACIL.bat"
        echo
        echo "🎯 USO SÚPER SIMPLE:"
        echo "   1. Cliente ejecuta INSTALAR_FACIL.bat"
        echo "   2. Usa el icono del escritorio"
        echo "   3. Configura en ventanas"
        echo "   4. ¡Funciona!"
        echo

    else
        echo "❌ No se generó el ejecutable"
    fi
else
    echo "❌ Error en compilación"
fi

echo
read -p "Presiona Enter para continuar..."
