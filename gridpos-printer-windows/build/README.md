# Carpeta Build

Esta carpeta contiene archivos necesarios para la construcción de la aplicación.

## Icono de la Aplicación

Para agregar un icono personalizado:

**Ruta exacta del icono:**
```
gridpos-printer-windows/build/icon.ico
```

**Pasos:**

1. Crear o convertir una imagen a formato `.ico`
2. El icono debe tener múltiples tamaños (16x16, 32x32, 48x48, 256x256)
3. Guardar el archivo como `icon.ico` en esta carpeta (`build/icon.ico`)
4. El instalador usará automáticamente este icono al construir la aplicación

**Ejemplo de estructura:**
```
gridpos-printer-windows/
├── build/
│   ├── icon.ico          ← Colocar el icono aquí
│   └── README.md
├── package.json          (configurado para usar build/icon.ico)
└── ...
```

### Herramientas para crear iconos

- **Online**: https://convertio.co/es/png-ico/
- **Windows**: Usar IcoFX o similar
- **Online**: https://www.icoconverter.com/

### Tamaños recomendados

- 16x16 píxeles
- 32x32 píxeles
- 48x48 píxeles
- 256x256 píxeles

Si no se proporciona un icono, Electron usará su icono por defecto.

