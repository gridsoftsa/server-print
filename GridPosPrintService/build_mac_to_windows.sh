#!/bin/bash

echo "========================================"
echo "    COMPILADOR MAC → WINDOWS"
echo "    GridPos Print Service"
echo "========================================"
echo

# Verificar que .NET esté instalado
if ! command -v dotnet &> /dev/null; then
    echo "❌ ERROR: .NET SDK no está instalado"
    echo
    echo "Instala .NET 6 para macOS desde:"
    echo "https://dotnet.microsoft.com/download/dotnet/6.0"
    echo
    read -p "Presiona Enter después de instalar .NET..."
    exit 1
fi

echo "✅ .NET SDK detectado"
dotnet --version
echo

# Verificar archivos necesarios
echo "🔍 Verificando archivos del proyecto..."
MISSING_FILES=0

if [ ! -f "GridPosPrintService.csproj" ]; then
    echo "❌ Falta: GridPosPrintService.csproj"
    MISSING_FILES=1
fi

if [ ! -f "GridPosPrintService.cs" ]; then
    echo "❌ Falta: GridPosPrintService.cs"
    MISSING_FILES=1
fi

if [ ! -f "Program.cs" ]; then
    echo "❌ Falta: Program.cs"
    MISSING_FILES=1
fi

if [ ! -f "GridPosPrintProcessor.cs" ]; then
    echo "❌ Falta: GridPosPrintProcessor.cs"
    MISSING_FILES=1
fi

if [ ! -f "RawPrinterHelper.cs" ]; then
    echo "❌ Falta: RawPrinterHelper.cs"
    MISSING_FILES=1
fi

if [ $MISSING_FILES -eq 1 ]; then
    echo
    echo "❌ ERROR: Faltan archivos necesarios"
    echo "   Ejecuta desde la carpeta: server-print/GridPosPrintService/"
    echo
    read -p "Presiona Enter para continuar..."
    exit 1
fi

echo "✅ Todos los archivos encontrados"
echo

# Limpiar compilaciones anteriores
echo "🧹 Limpiando compilaciones anteriores..."
rm -rf bin obj publish_windows 2>/dev/null
echo "✅ Limpieza completada"
echo

# Crear archivo temporal .csproj compatible con compilación cross-platform
echo "🔄 Creando configuración temporal..."
cp GridPosPrintService.csproj GridPosPrintService.csproj.backup

# Modificar temporalmente el target framework
sed 's/net6.0-windows/net6.0/g' GridPosPrintService.csproj.backup > GridPosPrintService.csproj.temp
sed 's/<UseWindowsForms>true<\/UseWindowsForms>//g' GridPosPrintService.csproj.temp > GridPosPrintService.csproj.temp2
sed 's/<OutputType>WinExe<\/OutputType>/<OutputType>Exe<\/OutputType>/g' GridPosPrintService.csproj.temp2 > GridPosPrintService.csproj

# Limpiar archivos temporales
rm -f GridPosPrintService.csproj.temp GridPosPrintService.csproj.temp2

echo "✅ Configuración temporal creada"
echo

# Restaurar dependencias
echo "📦 Restaurando dependencias..."
dotnet restore
if [ $? -ne 0 ]; then
    echo "❌ Error restaurando dependencias"
    mv GridPosPrintService.csproj.backup GridPosPrintService.csproj
    read -p "Presiona Enter para continuar..."
    exit 1
fi
echo "✅ Dependencias restauradas"
echo

# Compilar para Windows x64
echo "🔨 Compilando para Windows x64..."
echo "   Target: net6.0"
echo "   Runtime: win-x64"
echo "   Mode: Self-contained"
echo

dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:PublishTrimmed=false -o publish_windows

COMPILE_RESULT=$?

# Restaurar archivo original
mv GridPosPrintService.csproj.backup GridPosPrintService.csproj

if [ $COMPILE_RESULT -eq 0 ]; then
    echo
    echo "✅ COMPILACIÓN EXITOSA"
    echo
    echo "📁 Archivos generados en: publish_windows/"
    echo

    # Verificar archivo principal
    if [ -f "publish_windows/GridPosPrintService.exe" ]; then
        echo "🚀 Archivos compilados:"
        echo "   ✅ GridPosPrintService.exe"

        # Mostrar tamaño del archivo
        size=$(du -h "publish_windows/GridPosPrintService.exe" | cut -f1)
        echo "      Tamaño: $size"
    else
        echo "❌ Error: No se generó GridPosPrintService.exe"
        read -p "Presiona Enter para continuar..."
        exit 1
    fi

    # Copiar archivos auxiliares necesarios
    echo
    echo "📦 Copiando archivos auxiliares..."

    if [ -f "appsettings.json" ]; then
        cp "appsettings.json" "publish_windows/"
        echo "   ✅ appsettings.json"
    fi

    if [ -f "install_interactive.bat" ]; then
        cp "install_interactive.bat" "publish_windows/"
        echo "   ✅ install_interactive.bat"
    fi

    if [ -f "check_config.bat" ]; then
        cp "check_config.bat" "publish_windows/"
        echo "   ✅ check_config.bat"
    fi

    if [ -f "uninstall.bat" ]; then
        cp "uninstall.bat" "publish_windows/"
        echo "   ✅ uninstall.bat"
    fi

    if [ -f "README.md" ]; then
        cp "README.md" "publish_windows/"
        echo "   ✅ README.md"
    fi

    # Crear archivo de instrucciones para Windows
    cat > "publish_windows/INSTRUCCIONES_WINDOWS.txt" << 'EOF'
GRIDPOS PRINT SERVICE - COMPILADO DESDE MAC
==========================================

✅ Este ejecutable fue compilado desde macOS para Windows

INSTALACION:
-----------
1. Copia esta carpeta completa a Windows
2. Ejecuta como administrador: install_interactive.bat
3. Configura tu API y Client Slug
4. ¡Listo!

ARCHIVOS INCLUIDOS:
------------------
✅ GridPosPrintService.exe    - Servicio principal
✅ install_interactive.bat    - Instalador
✅ check_config.bat          - Verificador
✅ uninstall.bat             - Desinstalador
✅ appsettings.json          - Configuración

CONFIGURACION:
-------------
- API: Producción (api.gridpos.co) o Demo (api-demo.gridpos.co)
- Client Slug: Tu identificador único
- Authorization: f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3

BENEFICIOS:
----------
⚡ Respuesta en 2 segundos (vs 30 segundos anterior)
💾 Menos de 10MB RAM (vs 50-100MB anterior)
🛡️ Servicio Windows nativo
📡 Conexión directa a GridPos API

¡Disfruta tu servicio ultra rápido! 🚀
EOF

    echo "   ✅ INSTRUCCIONES_WINDOWS.txt"
    echo
    echo "✅ Archivos auxiliares copiados"
    echo
    echo "========================================"
    echo "       🎉 COMPILACIÓN COMPLETADA"
    echo "========================================"
    echo
    echo "📂 UBICACIÓN: $PWD/publish_windows/"
    echo
    echo "🚀 PRÓXIMOS PASOS:"
    echo "   1. Copia la carpeta 'publish_windows' a Windows"
    echo "   2. En Windows, ejecuta 'install_interactive.bat' como administrador"
    echo "   3. Configura tu API y Client Slug"
    echo "   4. ¡Disfruta de tu servicio nativo ultra rápido!"
    echo
    echo "📊 BENEFICIOS:"
    echo "   ⚡ Respuesta en 2 segundos (vs 30 segundos anterior)"
    echo "   💾 Menos de 10MB RAM (vs 50-100MB anterior)"
    echo "   🛡️ Servicio Windows nativo con auto-inicio"
    echo "   📡 Conexión directa a GridPos API"
    echo
    echo "¿Quieres abrir la carpeta 'publish_windows' ahora? (s/n)"
    read -p "Respuesta: " OPEN_FOLDER
    if [[ "$OPEN_FOLDER" == "s" || "$OPEN_FOLDER" == "S" ]]; then
        open "publish_windows" 2>/dev/null || echo "Abre manualmente: $PWD/publish_windows/"
    fi

else
    echo
    echo "❌ ERROR EN LA COMPILACIÓN"
    echo
    echo "🔍 POSIBLES SOLUCIONES:"
    echo "   1. Verificar que .NET 6 SDK esté correctamente instalado"
    echo "   2. Verificar conexión a internet para descargar dependencias"
    echo "   3. Verificar que todos los archivos .cs estén presentes"
    echo
    echo "📞 ¿NECESITAS AYUDA?"
    echo "   - Revisa los errores mostrados arriba"
    echo "   - Verifica la instalación de .NET 6"
    echo "   - Asegúrate de estar en la carpeta correcta"
fi

echo
echo "📧 Soporte: soporte@gridpos.com"
echo "🌐 Documentación: README.md"
echo
read -p "Presiona Enter para continuar..."
