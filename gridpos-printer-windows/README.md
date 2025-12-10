# GridPOS Printer - Aplicación Nativa para Windows

Aplicación nativa de Windows para impresión GridPOS con soporte WebSocket. Esta aplicación reemplaza la solución PHP anterior con una aplicación fácil de instalar y configurar.

## Características

- ✅ **Conexión WebSocket robusta** con reconexión automática y múltiples métodos de conexión
- ✅ **Manejo inteligente de errores 502** - Reintenta automáticamente hasta conectar (igual que Laravel)
- ✅ Impresión ESC/POS nativa para Windows
- ✅ Soporte para impresión de ventas y órdenes
- ✅ Apertura de cajón de dinero
- ✅ Interfaz gráfica minimalista y fácil de usar
- ✅ Ejecución en segundo plano (bandeja del sistema)
- ✅ Auto-inicio con Windows
- ✅ Instalador .exe para fácil distribución

## Requisitos

- Windows 10 o superior
- Impresora ESC/POS compatible instalada en Windows
- Conexión a Internet para WebSocket

## Instalación

### Opción 1: Instalador (Recomendado)

1. Descargar el instalador `GridPOS Printer Setup.exe`
2. Ejecutar el instalador
3. Seguir las instrucciones del asistente
4. La aplicación se instalará y ejecutará automáticamente

### Opción 2: Desarrollo

```bash
# Instalar dependencias (si tienes problemas con mirrors, usar --ignore-scripts)
npm install --ignore-scripts
cd node_modules/electron
ELECTRON_MIRROR=https://github.com/electron/electron/releases/download/ node install.js
cd ../..

# Ejecutar en modo desarrollo
npm start

# Construir instalador
npm run build:win
```

## Configuración

La configuración es muy simple, solo necesitas:

1. Abrir la aplicación desde el menú de inicio o el escritorio
2. Ingresar el **Slug del cliente** (ej: `matambre`)
   - El canal de impresión se generará automáticamente como `[slug]-server-print`
3. Seleccionar la **impresora** de la lista
4. Hacer clic en **"Guardar"**

**¡Eso es todo!** La aplicación:
- Se conectará automáticamente al servidor WebSocket
- Intentará múltiples métodos de conexión si hay errores 502
- Se reconectará automáticamente si se pierde la conexión
- Comenzará a recibir comandos de impresión inmediatamente

> **Nota:** Todos los demás valores (API Key, URLs, etc.) están preconfigurados y no necesitan cambiarse.

## Uso

Una vez configurada, la aplicación funciona completamente en segundo plano:

- ✅ Se ejecuta en la **bandeja del sistema** (tray)
- ✅ Se conecta automáticamente al servidor WebSocket
- ✅ **Maneja errores 502 automáticamente** - Reintenta con múltiples métodos hasta conectar
- ✅ Procesa automáticamente los comandos de impresión recibidos
- ✅ Se reconecta automáticamente si se pierde la conexión
- ✅ Se inicia automáticamente con Windows

### Acceso a la configuración

- **Doble clic** en el icono de la bandeja del sistema
- O seleccionar **"Abrir"** desde el menú contextual del icono

### Botones de control

- **🔌 Probar**: Valida la autenticación (no conecta WebSocket)
- **🔗 Conectar**: Conecta manualmente el WebSocket
- **❌ Desconectar**: Desconecta el WebSocket manualmente
- **💾 Guardar**: Guarda la configuración y reconecta automáticamente

### Indicador de estado

El indicador en la parte superior muestra:
- 🟢 **Verde**: Conectado al WebSocket
- 🔴 **Rojo**: Desconectado
- 🟡 **Amarillo**: Conectando...

## Eventos Soportados

La aplicación procesa los siguientes eventos del WebSocket:

- `salePrinter`: Imprime una venta (factura)
- `orderPrinter`: Imprime una orden
- `openCashDrawer`: Abre el cajón de dinero

## Solución de Problemas

### La aplicación no se conecta

La aplicación intenta automáticamente múltiples métodos de conexión (igual que Laravel). Si ves errores 502, es normal - la aplicación reintentará automáticamente hasta conectar.

1. Verificar que el **Slug del cliente** sea correcto
2. Verificar la conexión a Internet
3. Usar el botón **"Probar"** para validar la autenticación
4. Usar el botón **"Conectar"** para forzar la conexión manualmente
5. Revisar los logs en la consola (modo desarrollo) - verás los intentos de conexión

**Nota:** Los primeros 2-3 intentos pueden fallar con error 502, esto es normal. La aplicación seguirá reintentando hasta conectar exitosamente.

### La impresora no imprime

1. Verificar que la impresora esté instalada en Windows
2. Verificar que la impresora esté encendida y con papel
3. Usar el botón **🧪** junto al selector de impresora para probar
4. Verificar que el nombre de la impresora coincida exactamente

### La aplicación no inicia

1. Verificar que Windows 10 o superior esté instalado
2. Ejecutar como administrador si es necesario
3. Verificar que no haya otra instancia ejecutándose (la aplicación previene múltiples instancias)

## Desarrollo

### Estructura del Proyecto

```
gridpos-printer-windows/
├── main.js              # Proceso principal de Electron
├── preload.js           # Script de preload (seguridad)
├── package.json         # Configuración y dependencias
├── src/
│   ├── websocket-service.js    # Servicio WebSocket
│   ├── printer-service.js      # Servicio de impresión
│   └── config-manager.js       # Gestión de configuración
└── renderer/
    ├── index.html       # Interfaz de usuario
    ├── styles.css       # Estilos
    └── renderer.js      # Lógica de la interfaz
```

### Construir Instalador

```bash
# Construir para Windows
npm run build:win

# El instalador se generará en la carpeta dist/
# El archivo será: GridPOS Printer Setup X.X.X.exe
```

## Características Técnicas

### Manejo de Conexión WebSocket

La aplicación implementa el mismo comportamiento que el comando Laravel `ws:listen`:

- **Múltiples métodos de conexión**: Intenta 3 métodos diferentes automáticamente
  1. Conexión estándar con token en query + header Authorization
  2. Conexión con token solo en query string
  3. Socket.IO Engine.IO format (método que normalmente funciona)

- **Manejo de errores 502**: Cuando detecta un error 502, prueba inmediatamente el siguiente método sin esperar

- **Reconexión automática**: Usa backoff exponencial igual que Laravel (`retryDelay * 2^(retryCount-1)`)

- **Reintentos infinitos**: Por defecto reintenta indefinidamente hasta conectar (configurable)

### Valores Preconfigurados

- **API Key**: `your-secure-api-key-for-laravel-communication` (hardcoded)
- **WebSocket URL**: `wss://ws.gridpos.co`
- **Auth URL**: `https://ws.gridpos.co/api/auth/token`
- **Rol**: `user` (por defecto)
- **Retry Delay**: 3 segundos inicial

## Licencia

MIT

## Soporte

Para soporte técnico, contactar al equipo de desarrollo de GridPOS.

