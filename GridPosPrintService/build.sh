#!/bin/bash

echo "========================================"
echo "    COMPILADOR CROSS-PLATFORM"
echo "    GridPos Print Service"
echo "========================================"
echo

# Verificar que .NET 6 esté instalado
if ! command -v dotnet &> /dev/null; then
    echo "❌ ERROR: .NET 6 SDK no está instalado"
    echo
    echo "Descarga e instala .NET 6 SDK desde:"
    echo "https://dotnet.microsoft.com/download/dotnet/6.0"
    echo
    read -p "Presiona Enter para continuar..."
    exit 1
fi

echo "✅ .NET SDK detectado"
dotnet --version
echo

# Limpiar compilaciones anteriores
echo "🧹 Limpiando compilaciones anteriores..."
rm -rf bin obj 2>/dev/null
echo "✅ Limpieza completada"
echo

# Crear backup del proyecto original
echo "🔄 Creando configuración temporal para compilación..."
cp GridPosPrintService.csproj GridPosPrintService.csproj.backup

# Modificar temporalmente el target framework para compilación cross-platform
sed -i.tmp 's/net6.0-windows/net6.0/g' GridPosPrintService.csproj
sed -i.tmp 's/<UseWindowsForms>true<\/UseWindowsForms>//g' GridPosPrintService.csproj
sed -i.tmp 's/<OutputType>WinExe<\/OutputType>/<OutputType>Exe<\/OutputType>/g' GridPosPrintService.csproj

# Compilar para Windows x64
echo "🔨 Compilando para Windows x64..."
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:PublishTrimmed=false

# Restaurar archivo original
mv GridPosPrintService.csproj.backup GridPosPrintService.csproj
rm -f GridPosPrintService.csproj.tmp 2>/dev/null

if [ $? -eq 0 ]; then
    echo
    echo "✅ Compilación exitosa"
    echo
    echo "📁 Archivos generados en:"
    echo "    bin/Release/net6.0/win-x64/publish/"
    echo
    echo "🚀 Archivos principales:"
    echo "    ✓ GridPosPrintService.exe"
    echo "    ✓ Archivos de dependencias"
    echo

    # Copiar archivos auxiliares
    echo "📦 Copiando archivos auxiliares..."
    cp appsettings.json "bin/Release/net6.0/win-x64/publish/" 2>/dev/null
    cp install_interactive.bat "bin/Release/net6.0/win-x64/publish/" 2>/dev/null
    cp check_config.bat "bin/Release/net6.0/win-x64/publish/" 2>/dev/null
    cp uninstall.bat "bin/Release/net6.0/win-x64/publish/" 2>/dev/null
    cp README.md "bin/Release/net6.0/win-x64/publish/" 2>/dev/null

    echo "✅ Archivos auxiliares copiados"
    echo
    echo "========================================"
    echo "       COMPILACIÓN COMPLETADA"
    echo "========================================"
    echo
    echo "Para usar en Windows:"
    echo "  1. Copia la carpeta 'bin/Release/net6.0/win-x64/publish' a Windows"
    echo "  2. Ejecuta 'install_interactive.bat' como administrador"
    echo
    echo "¿Quieres abrir la carpeta de archivos compilados? (s/n)"
    read -p "Respuesta: " OPEN_FOLDER
    if [[ "$OPEN_FOLDER" == "s" || "$OPEN_FOLDER" == "S" ]]; then
        open "bin/Release/net6.0/win-x64/publish/" 2>/dev/null || \
        xdg-open "bin/Release/net6.0/win-x64/publish/" 2>/dev/null || \
        echo "Abre manualmente: bin/Release/net6.0/win-x64/publish/"
    fi
else
    echo
    echo "❌ Error en la compilación"
    echo "Revisa los errores mostrados arriba"
fi

echo
read -p "Presiona Enter para continuar..."
