// Estado de la aplicación
let config = {};
let printers = [];

// Elementos del DOM
const configForm = document.getElementById("configForm");
const statusDot = document.getElementById("statusDot");
const statusText = document.getElementById("statusText");
const messageDiv = document.getElementById("message");
const lastMessageEl = document.getElementById("lastMessage");
const refreshPrintersBtn = document.getElementById("refreshPrinters");
const testPrinterBtn = document.getElementById("testPrinter");
const testConnectionBtn = document.getElementById("testConnection");
const connectBtn = document.getElementById("connectBtn");
const disconnectBtn = document.getElementById("disconnectBtn");
const logsContainer = document.getElementById("logsContainer");
const clearLogsBtn = document.getElementById("clearLogs");

// Generar Business ID automáticamente cuando se escribe en userId
function setupBusinessIdAutoGeneration() {
    const userIdInput = document.getElementById("userId");
    const businessIdInput = document.getElementById("businessId");

    if (userIdInput && businessIdInput) {
        userIdInput.addEventListener("input", (e) => {
            const userId = e.target.value.trim();
            if (userId) {
                businessIdInput.value = `${userId}-server-print`;
            } else {
                businessIdInput.value = "";
            }
        });
    }
}

// Cargar configuración al iniciar
async function loadConfig() {
    try {
        config = await window.electronAPI.getConfig();
        populateForm(config);
        await loadPrinters();
        updateStatus();
        setupBusinessIdAutoGeneration();
    } catch (error) {
        showMessage("Error cargando configuración: " + error.message, "error");
    }
}

// Poblar formulario con configuración
function populateForm(config) {
    // Campos visibles
    document.getElementById("userId").value = config.userId || "";
    document.getElementById("defaultPrinter").value =
        config.defaultPrinter || "";

    // Checkbox de auto-start (solo en Windows)
    const autoStartCheckbox = document.getElementById("autoStart");
    if (autoStartCheckbox) {
        autoStartCheckbox.checked = config.autoStart !== false; // Por defecto true
    }

    // Campos ocultos (valores por defecto)
    document.getElementById("apiKey").value =
        config.apiKey || "your-secure-api-key-for-laravel-communication";
    const userId = config.userId || "";
    const businessId =
        config.businessId || (userId ? `${userId}-server-print` : "");
    document.getElementById("businessId").value = businessId;
    document.getElementById("role").value = config.role || "user";
    document.getElementById("wsUrl").value =
        config.wsUrl || "wss://ws.gridpos.co";
    document.getElementById("authUrl").value =
        config.authUrl || "https://ws.gridpos.co/api/auth/token";
    document.getElementById("autoConnect").value =
        config.autoConnect !== false ? "true" : "false";
    document.getElementById("retryDelay").value = config.retryDelay || 3;
}

// Cargar impresoras disponibles
async function loadPrinters() {
    try {
        const printerSelect = document.getElementById("defaultPrinter");
        printerSelect.innerHTML = '<option value="">Cargando...</option>';

        printers = await window.electronAPI.getPrinters();

        printerSelect.innerHTML =
            '<option value="">Seleccione una impresora</option>';
        printers.forEach((printer) => {
            const option = document.createElement("option");
            option.value = printer;
            option.textContent = printer;
            if (config.defaultPrinter === printer) {
                option.selected = true;
            }
            printerSelect.appendChild(option);
        });
    } catch (error) {
        showMessage("Error cargando impresoras: " + error.message, "error");
        document.getElementById("defaultPrinter").innerHTML =
            '<option value="">Error al cargar</option>';
    }
}

// Construir configuración a partir del formulario (reutilizable)
function buildConfigFromForm() {
    const formData = new FormData(configForm);

    // Obtener userId del formulario (único campo editable)
    const userIdInput = formData.get("userId");
    const userId = userIdInput ? userIdInput.trim() : "";

    // Generar Business ID automáticamente desde userId
    // También verificar el campo oculto por si ya fue actualizado por setupBusinessIdAutoGeneration
    const businessIdHidden = formData.get("businessId");
    let businessId = businessIdHidden ? businessIdHidden.trim() : "";

    // Si no hay businessId pero sí userId, generarlo automáticamente
    if (!businessId && userId) {
        businessId = `${userId}-server-print`;
    }

    // Obtener valor del checkbox de auto-start
    const autoStartCheckbox = document.getElementById("autoStart");
    const autoStart = autoStartCheckbox ? autoStartCheckbox.checked : true; // Por defecto true

    // Obtener otros campos con valores por defecto (pueden ser null si están ocultos)
    const apiKeyInput = formData.get("apiKey");
    const roleInput = formData.get("role");
    const wsUrlInput = formData.get("wsUrl");
    const authUrlInput = formData.get("authUrl");
    const defaultPrinterInput = formData.get("defaultPrinter");
    const autoConnectInput = formData.get("autoConnect");
    const retryDelayInput = formData.get("retryDelay");

    return {
        apiKey:
            (apiKeyInput && apiKeyInput.trim()) ||
            "your-secure-api-key-for-laravel-communication",
        userId: userId,
        businessId: businessId,
        role: (roleInput && roleInput.trim()) || "user",
        wsUrl: (wsUrlInput && wsUrlInput.trim()) || "wss://ws.gridpos.co",
        authUrl:
            (authUrlInput && authUrlInput.trim()) ||
            "https://ws.gridpos.co/api/auth/token",
        defaultPrinter: defaultPrinterInput ? defaultPrinterInput.trim() : "",
        autoConnect:
            autoConnectInput === "true" ||
            autoConnectInput === null ||
            autoConnectInput === "",
        autoStart: autoStart,
        retryDelay: retryDelayInput ? parseInt(retryDelayInput) : 3,
    };
}

// Guardar configuración
configForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const newConfig = buildConfigFromForm();

    try {
        await window.electronAPI.saveConfig(newConfig);
        config = newConfig;
        showMessage(
            "✅ Configuración guardada correctamente. La conexión se reiniciará automáticamente.",
            "success"
        );

        // Actualizar estado después de un momento
        setTimeout(() => {
            updateStatus();
        }, 2000);
    } catch (error) {
        showMessage(
            "❌ Error guardando configuración: " + error.message,
            "error"
        );
    }
});

// Probar conexión
testConnectionBtn.addEventListener("click", async () => {
    testConnectionBtn.disabled = true;
    testConnectionBtn.innerHTML = '<span class="loading"></span> Probando...';

    try {
        // Usar siempre la configuración actual del formulario (aunque el usuario no haya pulsado Guardar)
        const tempConfig = buildConfigFromForm();
        await window.electronAPI.saveConfig(tempConfig);

        const result = await window.electronAPI.testConnection();
        if (result.success) {
            showMessage(
                "✅ Conexión exitosa. Autenticación correcta.",
                "success"
            );
            // Actualizar estado después de un momento para reflejar la conexión WebSocket
            setTimeout(() => {
                updateStatus();
            }, 2000);
            // Seguir actualizando el estado cada segundo por unos momentos
            let updateCount = 0;
            const statusInterval = setInterval(() => {
                updateStatus();
                updateCount++;
                if (updateCount >= 5) {
                    clearInterval(statusInterval);
                }
            }, 1000);
        } else {
            showMessage(
                "❌ Error de conexión: " + (result.error || "Desconocido"),
                "error"
            );
        }
    } catch (error) {
        showMessage("❌ Error de conexión: " + error.message, "error");
    } finally {
        testConnectionBtn.disabled = false;
        testConnectionBtn.innerHTML = "🔌 Probar Conexión";
    }
});

// Conectar WebSocket manualmente
connectBtn.addEventListener("click", async () => {
    connectBtn.disabled = true;
    connectBtn.innerHTML = '<span class="loading"></span> Conectando...';

    try {
        await window.electronAPI.connectWebSocket();
        showMessage("🔄 Intentando conectar WebSocket...", "info");
        // Actualizar estado cada segundo por unos momentos
        let updateCount = 0;
        const statusInterval = setInterval(() => {
            updateStatus();
            updateCount++;
            if (updateCount >= 10) {
                clearInterval(statusInterval);
            }
        }, 1000);
    } catch (error) {
        showMessage("❌ Error conectando: " + error.message, "error");
    } finally {
        connectBtn.disabled = false;
        connectBtn.innerHTML = "🔗 Conectar WebSocket";
    }
});

// Desconectar WebSocket manualmente
disconnectBtn.addEventListener("click", async () => {
    disconnectBtn.disabled = true;
    disconnectBtn.innerHTML = '<span class="loading"></span> Desconectando...';

    try {
        await window.electronAPI.disconnectWebSocket();
        showMessage("⚠️ Desconectando WebSocket...", "info");
        setTimeout(() => {
            updateStatus();
        }, 1000);
    } catch (error) {
        showMessage("❌ Error desconectando: " + error.message, "error");
    } finally {
        disconnectBtn.disabled = false;
        disconnectBtn.innerHTML = "❌ Desconectar";
    }
});

// Actualizar lista de impresoras
refreshPrintersBtn.addEventListener("click", async () => {
    refreshPrintersBtn.disabled = true;
    refreshPrintersBtn.innerHTML = '<span class="loading"></span>';

    await loadPrinters();

    refreshPrintersBtn.disabled = false;
    refreshPrintersBtn.innerHTML = "🔄 Actualizar";
    showMessage("✅ Lista de impresoras actualizada", "success");
});

// Probar impresora
testPrinterBtn.addEventListener("click", async () => {
    const printerSelect = document.getElementById("defaultPrinter");
    const printerName = printerSelect.value;

    if (!printerName) {
        showMessage("⚠️ Por favor seleccione una impresora primero", "info");
        return;
    }

    testPrinterBtn.disabled = true;
    testPrinterBtn.innerHTML = '<span class="loading"></span> Imprimiendo...';

    try {
        const result = await window.electronAPI.testPrinter(printerName);
        if (result.success) {
            showMessage(
                "✅ Prueba de impresión enviada correctamente",
                "success"
            );
        } else {
            showMessage(
                "❌ Error en impresión: " + (result.error || "Desconocido"),
                "error"
            );
        }
    } catch (error) {
        showMessage("❌ Error en impresión: " + error.message, "error");
    } finally {
        testPrinterBtn.disabled = false;
        testPrinterBtn.innerHTML = "🧪 Probar Impresora";
    }
});

// Actualizar estado de conexión
async function updateStatus() {
    try {
        const status = await window.electronAPI.getStatus();

        if (status.connected) {
            statusDot.className = "status-dot connected";
            statusText.textContent = "Conectado";
            // Mostrar botón desconectar y ocultar conectar
            if (connectBtn) connectBtn.style.display = "none";
            if (disconnectBtn) disconnectBtn.style.display = "inline-block";
        } else {
            statusDot.className = "status-dot disconnected";
            statusText.textContent = status.connecting
                ? "Conectando..."
                : "Desconectado";
            // Mostrar botón conectar y ocultar desconectar
            if (connectBtn) connectBtn.style.display = "inline-block";
            if (disconnectBtn) disconnectBtn.style.display = "none";
        }

        if (status.lastMessage) {
            const date = new Date(status.lastMessage);
            lastMessageEl.textContent = `Último mensaje: ${date.toLocaleString(
                "es-CO"
            )}`;
        } else {
            lastMessageEl.textContent = "Último mensaje: Nunca";
        }
    } catch (error) {
        console.error("Error actualizando estado:", error);
    }
}

// Actualizar estado más frecuentemente cuando está intentando conectar
let statusUpdateInterval = null;
function startStatusUpdates() {
    if (statusUpdateInterval) return;

    statusUpdateInterval = setInterval(() => {
        updateStatus();
    }, 2000); // Actualizar cada 2 segundos
}

function stopStatusUpdates() {
    if (statusUpdateInterval) {
        clearInterval(statusUpdateInterval);
        statusUpdateInterval = null;
    }
}

// Escuchar eventos de WebSocket
window.electronAPI.onWebSocketStatus((data) => {
    if (data.connected) {
        statusDot.className = "status-dot connected";
        statusText.textContent = "Conectado";
        showMessage("✅ Conectado al servidor WebSocket", "success");
        stopStatusUpdates(); // Dejar de actualizar frecuentemente cuando está conectado
    } else {
        statusDot.className = "status-dot disconnected";
        statusText.textContent = "Desconectado";
        showMessage("⚠️ Desconectado del servidor WebSocket", "info");
        startStatusUpdates(); // Actualizar frecuentemente cuando está desconectado
    }
    updateStatus();
});

window.electronAPI.onWebSocketError((data) => {
    showMessage("❌ Error de WebSocket: " + data.error, "error");
    statusDot.className = "status-dot disconnected";
    statusText.textContent = "Error";
});

// Mostrar mensaje
function showMessage(text, type = "info") {
    messageDiv.textContent = text;
    messageDiv.className = `message ${type}`;
    messageDiv.classList.remove("hidden");

    // Ocultar después de 5 segundos si es éxito o info
    if (type === "success" || type === "info") {
        setTimeout(() => {
            messageDiv.classList.add("hidden");
        }, 5000);
    }
}

// Actualizar estado cada 5 segundos cuando está conectado
setInterval(updateStatus, 5000);

// Iniciar actualizaciones frecuentes si está desconectado
startStatusUpdates();

// Función para agregar logs visuales
function addLog(message, type = "info") {
    if (!logsContainer) return;

    const timestamp = new Date().toLocaleTimeString("es-ES", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });

    const logEntry = document.createElement("div");
    logEntry.className = `log-entry ${type}`;
    logEntry.innerHTML = `<span class="timestamp">[${timestamp}]</span>${message}`;

    logsContainer.appendChild(logEntry);

    // Auto-scroll al final
    logsContainer.scrollTop = logsContainer.scrollHeight;

    // Limitar a 200 entradas para no sobrecargar
    while (logsContainer.children.length > 200) {
        logsContainer.removeChild(logsContainer.firstChild);
    }
}

// Limpiar logs
if (clearLogsBtn) {
    clearLogsBtn.addEventListener("click", () => {
        if (logsContainer) {
            logsContainer.innerHTML =
                '<div class="log-entry">Logs limpiados...</div>';
        }
    });
}

// Configurar listener de logs cuando el DOM esté listo
function setupLogListener() {
    if (window.electronAPI && window.electronAPI.onLog) {
        // Remover listeners anteriores si existen
        window.electronAPI.removeAllListeners("app-log");

        // Configurar nuevo listener
        // En preload onLog ya nos entrega solo "data", no el evento
        window.electronAPI.onLog((data) => {
            if (data && data.message) {
                addLog(data.message, data.type || "info");
            }
        });
        addLog("✅ Listener de logs configurado y listo", "success");
        return true;
    } else {
        addLog("⚠️ electronAPI.onLog no disponible", "warning");
        return false;
    }
}

// Configurar listener inmediatamente (puede que el DOM ya esté listo)
setupLogListener();

// Esperar a que el DOM esté completamente cargado
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        console.log("DOM cargado, reconfigurando listener...");
        setupLogListener(); // Reconfigurar por si acaso
        loadConfig();
        addLog("Aplicación iniciada", "success");
    });
} else {
    // DOM ya está cargado
    console.log("DOM ya cargado");
    loadConfig();
    addLog("Aplicación iniciada", "success");
}
