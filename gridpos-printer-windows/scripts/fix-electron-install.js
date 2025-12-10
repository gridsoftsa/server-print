#!/usr/bin/env node

/**
 * Script para forzar el uso del mirror oficial de GitHub para Electron
 * Esto evita problemas con configuraciones globales de npm que usan Taobao
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const electronPath = path.join(__dirname, '..', 'node_modules', 'electron');
const installJsPath = path.join(electronPath, 'install.js');

if (!fs.existsSync(installJsPath)) {
  console.log('⚠️  install.js de Electron no encontrado, saltando...');
  process.exit(0);
}

console.log('🔧 Configurando Electron para usar mirror oficial...');

// Leer el archivo install.js
let installJs = fs.readFileSync(installJsPath, 'utf8');

// Verificar si ya tiene la configuración correcta
if (installJs.includes('github.com/electron/electron/releases')) {
  console.log('✅ Electron ya está configurado correctamente');
  process.exit(0);
}

// Intentar ejecutar install.js con variables de entorno
try {
  process.env.ELECTRON_MIRROR = 'https://github.com/electron/electron/releases/download/';
  process.env.ELECTRON_CUSTOM_DIR = '{{ version }}';
  
  // Ejecutar install.js con las variables de entorno
  execSync('node install.js', {
    cwd: electronPath,
    stdio: 'inherit',
    env: {
      ...process.env,
      ELECTRON_MIRROR: 'https://github.com/electron/electron/releases/download/',
      ELECTRON_CUSTOM_DIR: '{{ version }}',
      npm_config_electron_mirror: 'https://github.com/electron/electron/releases/download/'
    }
  });
  
  console.log('✅ Electron instalado correctamente');
} catch (error) {
  console.error('❌ Error instalando Electron:', error.message);
  console.log('⚠️  Continuando con la instalación...');
  process.exit(0); // No fallar la instalación completa
}

