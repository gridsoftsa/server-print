#!/bin/bash
# Script para descargar Electron para Windows manualmente

set -e

VERSION="28.3.3"
ARCH="win32-x64"
CACHE_DIR=".electron"
ZIP_FILE="${CACHE_DIR}/electron-v${VERSION}-${ARCH}.zip"
EXTRACT_DIR="${CACHE_DIR}/electron-v${VERSION}-${ARCH}"

echo "📥 Descargando Electron ${VERSION} para Windows..."

# Crear directorio de cache si no existe
mkdir -p "${CACHE_DIR}"

# Descargar desde GitHub si no existe
if [ ! -f "${ZIP_FILE}" ]; then
    echo "Descargando desde GitHub..."
    curl -L -o "${ZIP_FILE}" \
        "https://github.com/electron/electron/releases/download/v${VERSION}/electron-v${VERSION}-${ARCH}.zip"
    echo "✅ Descarga completada"
else
    echo "✅ Archivo ya existe: ${ZIP_FILE}"
fi

# Descomprimir si no está descomprimido
if [ ! -d "${EXTRACT_DIR}" ]; then
    echo "Descomprimiendo..."
    unzip -q "${ZIP_FILE}" -d "${EXTRACT_DIR}"
    echo "✅ Descompresión completada"
else
    echo "✅ Ya descomprimido"
fi

echo "✅ Electron listo en: ${EXTRACT_DIR}"

