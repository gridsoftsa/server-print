# 📋 Manual de Instalación Básica - NSSM GridPOS

## 🎯 Configuración Específica

-   **Servicio**: `gridpos`
-   **Entorno**: Laragon instalado
-   **PHP**: Versión 8.2
-   **Proyecto**: GridPOS WebSocket

---

## 🚀 Paso 1: Verificar Laragon

### ✅ Requisitos Previos

1. **Laragon** debe estar instalado en `C:\laragon\`
2. **Apache** y **MySQL** deben estar ejecutándose
3. **Proyecto GridPOS** debe estar en `C:\laragon\www\server-print\`

### 🔍 Verificar PHP 8.2

1. Abre **Laragon**
2. Haz clic derecho en **Laragon** → **PHP** → **Version**
3. Selecciona **PHP 8.2** (ej: `php-8.2`)
4. Anota la ruta: `C:\laragon\bin\php\php-8.2\php.exe`

---

## 📦 Paso 2: Instalar NSSM

### 2.1 Descargar NSSM

1. Ve a: https://nssm.cc/download
2. Descarga **nssm-2.24.zip**
3. Extrae en `C:\nssm\`

### 2.2 Verificar Instalación

```cmd
C:\nssm\nssm.exe
```

---

## ⚙️ Paso 3: Crear Servicio gridpos

### 3.1 Instalar Servicio

```cmd
C:\nssm\nssm.exe install gridpos
```

### 3.2 Configurar Servicio

En la ventana que se abre:

#### **Pestaña "Application"**

-   **Path**: `C:\laragon\bin\php\php-8.2\php.exe`
-   **Startup directory**: `C:\laragon\www\server-print`
-   **Arguments**: `artisan ws:listen`

#### **Pestaña "Details"**

-   **Display name**: `gridpos`
-   **Description**: `Servicio WebSocket para GridPOS`
-   **Startup type**: `Automatic`

#### **Pestaña "Log on"**

-   **Log on as**: `Local System account`
-   ✅ Marca: `Allow service to interact with desktop`

### 3.3 Guardar

1. Haz clic en **"Install service"**
2. Cierra la ventana

---

## 🚀 Paso 4: Iniciar Servicio

### 4.1 Iniciar

```cmd
C:\nssm\nssm.exe start gridpos
```

### 4.2 Verificar Estado

```cmd
C:\nssm\nssm.exe status gridpos
```

### 4.3 Ver Logs

```cmd
C:\nssm\nssm.exe rotatelogs gridpos
```

---

## 🔧 Paso 5: Comandos Útiles

### Gestión del Servicio

```cmd
# Iniciar
C:\nssm\nssm.exe start gridpos

# Detener
C:\nssm\nssm.exe stop gridpos

# Reiniciar
C:\nssm\nssm.exe restart gridpos

# Ver estado
C:\nssm\nssm.exe status gridpos

# Ver logs
C:\nssm\nssm.exe rotatelogs gridpos
```

### Configuración

```cmd
# Editar configuración
C:\nssm\nssm.exe edit gridpos

# Ver configuración
C:\nssm\nssm.exe get gridpos

# Eliminar servicio
C:\nssm\nssm.exe remove gridpos confirm
```

---

## ✅ Paso 6: Verificación

### 6.1 Verificar Servicio

1. Abre **Servicios** (`services.msc`)
2. Busca **"gridpos"**
3. Estado debe ser **"En ejecución"**

### 6.2 Verificar WebSocket

1. Asegúrate de que **Laragon** esté ejecutándose
2. Abre navegador: `https://server-print.test`
3. Debe mostrar la página de WebSocket

### 6.3 Verificar Proyecto

1. Prueba: `https://server-print.test`
2. Debe funcionar correctamente

---

## 🛠️ Solución de Problemas

### El Servicio No Inicia

1. ✅ Verifica que **Laragon** esté ejecutándose
2. ✅ Verifica la ruta de PHP: `C:\laragon\bin\php\php-8.2\php.exe`
3. ✅ Verifica la ruta del proyecto: `C:\laragon\www\server-print`
4. ✅ Revisa logs: `C:\nssm\nssm.exe rotatelogs gridpos`

**¡Servicio gridpos listo para funcionar!** 🚀
