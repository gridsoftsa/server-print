# 🚀 Guía de Instalación - GridPos Printer Service

## 📋 **¿Qué hace esta aplicación?**

**Exactamente lo mismo que PHP pero mejor:**

-   ✅ **Lee la cola de impresión** de tu servidor
-   ✅ **Imprime tickets** (modo imagen y ESC/POS)
-   ✅ **Abre caja registradora**
-   ✅ **Imprime órdenes** (ESC/POS)
-   ✅ **Elimina trabajos** de la cola automáticamente

**Pero con ventajas:**

-   ⚡ **200ms (0.2 segundos)** vs 5 segundos de PHP
-   💻 **Muy bajo uso de CPU**
-   🖥️ **Interfaz gráfica** - No solo comandos
-   🔧 **Sin Laragon ni PHP** - Solo Windows
-   🚀 **Velocidad configurable** - Desde 200ms hasta 2 segundos

---

## 📦 **Archivos que necesitas:**

### **Solo estos 3 archivos:**

1. **`GridPosPrinter.exe`** - La aplicación principal
2. **`GridPosPrinter.exe.config`** - Configuración
3. **`install.bat`** - Instalador automático

---

## 🔧 **Paso 1: Preparar archivos**

### **1. Crear carpeta:**

```bash
mkdir C:\GridPos
```

### **2. Copiar archivos:**

```bash
# Copiar estos 3 archivos a C:\GridPos\
copy GridPosPrinter.exe C:\GridPos\
copy GridPosPrinter.exe.config C:\GridPos\
copy install.bat C:\GridPos\
```

---

## ⚙️ **Paso 2: Configurar**

### **Editar `GridPosPrinter.exe.config`:**

```xml
<?xml version="1.0" encoding="utf-8" ?>
<configuration>
  <appSettings>
    <!-- URL de tu API (cambiar por tu URL real) -->
    <add key="ApiUrl" value="https://api.gridpos.co/print-queue" />

    <!-- Tu identificador de cliente (cambiar por tu slug) -->
    <add key="ClientSlug" value="tu-client-slug" />

    <!-- Token de autenticación (ya está configurado) -->
    <add key="AuthToken" value="f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3" />

    <!-- Configuración de impresoras (opcional) -->
    <add key="DefaultPrinter" value="" />
    <add key="CashDrawerPrinter" value="" />
  </appSettings>
</configuration>
```

**Cambiar solo estas 2 líneas:**

-   `ApiUrl`: Tu URL del servidor
-   `ClientSlug`: Tu identificador de cliente

---

## 🚀 **Paso 3: Instalar**

### **Opción A: Instalación automática (Recomendado)**

```bash
# 1. Ir a la carpeta
cd C:\GridPos

# 2. Ejecutar como administrador
install.bat
```

### **Opción B: Instalación manual**

```bash
# 1. Crear tarea programada para inicio automático
schtasks /create /tn "GridPosPrinterService" /tr "C:\GridPos\GridPosPrinter.exe" /sc onstart /ru "SYSTEM" /f

# 2. Crear acceso directo en escritorio
powershell -Command "$WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%USERPROFILE%\Desktop\GridPos Printer.lnk'); $Shortcut.TargetPath = 'C:\GridPos\GridPosPrinter.exe'; $Shortcut.Save()"
```

---

## 🎮 **Paso 4: Usar la aplicación**

### **1. Iniciar:**

-   **Doble clic** en "GridPos Printer" del escritorio
-   **O** ejecutar: `C:\GridPos\GridPosPrinter.exe`

### **2. Configurar (opcional):**

-   **API URL**: Verificar que sea tu URL correcta
-   **Velocidad**: Seleccionar velocidad desde el dropdown
-   Ultra Rápido (200ms) - Máxima velocidad
-   Muy Rápido (500ms) - Alta velocidad
-   Rápido (1000ms) - Velocidad media
-   Normal (2000ms) - Velocidad estándar
-   **Personalizado**: Escribir valor en milisegundos

### **3. Iniciar servicio:**

-   Hacer clic en **"🚀 Iniciar Servicio"**
-   Ver logs en tiempo real
-   El servicio verificará la cola cada 200ms (ultra rápido)

---

## ✅ **Verificar que funciona:**

### **1. Logs esperados:**

```
[14:30:15.123] 🚀 GridPos Printer Service - Ultra Fast iniciado
[14:30:15.124] API URL: https://api.gridpos.co/print-queue
[14:30:15.125] Client Slug: tu-client-slug
[14:30:15.126] Velocidad: Ultra Rápido (200ms)
[14:30:15.127] ⚡ Configurado para máxima velocidad
[14:30:15.327] ✅ Servicio iniciado - Ultra Fast Mode
[14:30:15.328] ⚡ Verificando cada 200ms
[14:30:15.528] 📨 Encontrados 0 trabajos de impresión
```

### **2. Probar impresión:**

-   Desde tu aplicación web, mandar imprimir un ticket
-   Deberías ver en los logs:

```
[14:30:15.728] 📨 Encontrados 1 trabajos de impresión
[14:30:15.729] 🖨️ Procesando: salePrinter en tu-impresora
[14:30:15.730] ✅ Trabajo completado: salePrinter
```

---

## 🔧 **Solución de problemas:**

### **Error: "No se puede conectar"**

-   Verificar que la **API URL** sea correcta
-   Verificar que el **Client Slug** sea correcto
-   Verificar conexión a internet

### **Error: "No imprime"**

-   Verificar que la impresora esté conectada
-   Verificar que la impresora esté compartida
-   Verificar permisos de impresión

### **Error: "No inicia automáticamente"**

-   Ejecutar `install.bat` como administrador
-   Verificar que la tarea programada se creó:

```bash
schtasks /query /tn "GridPosPrinterService"
```

---

## 📊 **Comparación con PHP:**

| Funcionalidad        | PHP + Laragon | Solución Nativa |
| -------------------- | ------------- | --------------- |
| **Leer cola**        | ✅            | ✅              |
| **Imprimir tickets** | ✅            | ✅              |
| **Abrir caja**       | ✅            | ✅              |
| **ESC/POS**          | ✅            | ✅              |
| **Velocidad**        | 5 segundos    | 200ms (0.2s)    |
| **Uso de CPU**       | Alto          | Muy bajo        |
| **Instalación**      | Compleja      | Simple          |

---

## 🎯 **Resultado:**

### **Antes (PHP):**

-   Cliente instala Laragon (500MB)
-   Configura PHP y Apache
-   Scripts batch complejos
-   Alto uso de recursos

### **Ahora (Nativo):**

-   Cliente ejecuta instalador (5MB)
-   Interfaz gráfica profesional
-   Muy bajo uso de recursos
-   Inicio automático

---

## ✅ **¡Listo!**

Tu aplicación nativa hace **exactamente lo mismo** que PHP pero:

-   **Mucho más rápido** (200ms vs 5 segundos = 25x más rápido)
-   **Más eficiente** (menos CPU)
-   **Más fácil** (interfaz gráfica)
-   **Más estable** (sin dependencias)
-   **Velocidad configurable** (200ms a 2 segundos)

¡El cliente solo ejecuta `install.bat` y listo! 🚀
