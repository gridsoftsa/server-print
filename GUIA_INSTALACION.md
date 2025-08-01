# 🚀 GridPos Printer Service - Guía de Instalación

## 📋 **Descripción**

Aplicación nativa de Windows que reemplaza la solución PHP para procesar trabajos de impresión de GridPos.

### ✅ **Ventajas sobre PHP:**

-   ⚡ **200ms (0.2 segundos)** vs 5 segundos de PHP
-   💻 **Muy bajo uso de CPU**
-   🔧 **Sin Laragon ni PHP** - Solo Windows
-   🚀 **Velocidad configurable** - Desde 200ms hasta 2 segundos
-   🔄 **Inicio automático** - Se ejecuta con Windows
-   📱 **Interfaz gráfica** - No solo comandos

---

## 📦 **Archivos que necesitas:**

### **Solo 1 archivo:**

1. **`GridPosPrinter.exe`** - Instalador único (Todo en uno)

---

## 🚀 **Instalación (Paso a Paso)**

### **1. Descargar:**

-   Descargar `GridPosPrinter.exe` a tu computadora

### **2. Instalar:**

```bash
# 1. Clic derecho en GridPosPrinter.exe
# 2. Seleccionar "Ejecutar como administrador"
```

### **3. Configurar:**

Durante la instalación te pedirá:

-   **📝 Client Slug**: Tu identificador de cliente (obligatorio)
-   **🌐 API URL**: Tu URL del servidor (opcional, tiene valor por defecto)

### **4. ¡Listo!**

-   ✅ Se instala automáticamente en `C:\GridPos\`
-   ✅ Se configura para inicio automático con Windows
-   ✅ Se crea acceso directo en el escritorio
-   ✅ Se inicia inmediatamente en segundo plano

---

## 🎯 **Uso**

### **Inicio automático:**

-   El servicio se ejecuta automáticamente con Windows
-   Funciona en segundo plano sin interfaz visible

### **Inicio manual:**

-   Doble clic en "GridPos Printer" del escritorio
-   O ejecutar: `C:\GridPos\start_service.bat`

### **Monitoreo:**

-   Logs disponibles en: `C:\GridPos\logs\gridpos-printer.log`
-   Estadísticas cada 100 peticiones

---

## ⚙️ **Configuración**

### **Modificar configuración:**

1. Editar: `C:\GridPos\GridPosPrinter.ps1`
2. Cambiar las variables al inicio del archivo:
    ```powershell
    $ApiUrl = "tu-url"
    $ClientSlug = "tu-client-slug"
    $Interval = 200  # Velocidad en ms
    ```

### **Reiniciar servicio:**

1. Detener: `taskkill /f /im powershell.exe`
2. Iniciar: Doble clic en "GridPos Printer" del escritorio

---

## 📊 **Comparación con PHP**

| Funcionalidad         | PHP + Laragon | Solución Nativa |
| --------------------- | ------------- | --------------- |
| **Velocidad**         | 5 segundos    | 200ms (0.2s)    |
| **Uso de CPU**        | Alto          | Muy bajo        |
| **Dependencias**      | Laragon + PHP | Solo Windows    |
| **Instalación**       | Compleja      | 1 clic          |
| **Inicio automático** | Manual        | Automático      |
| **Interfaz**          | Solo comandos | Gráfica         |

---

## 🎯 **Resultado**

Tu aplicación nativa hace **exactamente lo mismo** que PHP pero:

-   **Mucho más rápido** (200ms vs 5 segundos = 25x más rápido)
-   **Más eficiente** (menos CPU)
-   **Más fácil** (interfaz gráfica)
-   **Más estable** (sin dependencias)
-   **Velocidad configurable** (200ms a 2 segundos)

---

## 🔧 **Solución de Problemas**

### **Error de permisos:**

-   Ejecutar como administrador

### **No se conecta:**

-   Verificar URL y Client Slug en `C:\GridPos\GridPosPrinter.ps1`

### **No inicia automáticamente:**

-   Verificar tarea programada en "Programador de tareas"

### **Ver logs:**

-   Abrir: `C:\GridPos\logs\gridpos-printer.log`

---

## 📞 **Soporte**

Si tienes problemas:

1. Verificar logs en `C:\GridPos\logs\gridpos-printer.log`
2. Verificar configuración en `C:\GridPos\GridPosPrinter.ps1`
3. Reiniciar el servicio

**¡Listo! Tu servicio de impresión nativo está funcionando.** 🚀
