const {
    app,
    BrowserWindow,
    ipcMain,
    Tray,
    Menu,
    nativeImage,
} = require("electron");
const path = require("path");
const WebSocketService = require("./src/websocket-service");
const PrinterService = require("./src/printer-service");
const ConfigManager = require("./src/config-manager");
const setupAutoStart = require("./src/auto-start");

let mainWindow;
let tray;
let websocketService;
let printerService;
let configManager;
let logBuffer = []; // Buffer para logs antes de que la ventana esté lista
let isWindowReady = false;

// Función helper para enviar logs a la UI (debe estar disponible globalmente)
function sendLogToUI(message, type = "info") {
    // Siempre mostrar en consola
    console.log(`[${type.toUpperCase()}] ${message}`);

    // Si la ventana está lista, enviar inmediatamente
    if (isWindowReady && mainWindow && !mainWindow.isDestroyed()) {
        try {
            mainWindow.webContents.send("app-log", { message, type });
        } catch (error) {
            console.error("Error enviando log a UI:", error.message);
        }
    } else {
        // Si no está lista, guardar en buffer
        logBuffer.push({ message, type, timestamp: Date.now() });
    }
}

// Evitar múltiples instancias
const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
    app.quit();
} else {
    app.on("second-instance", () => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.focus();
        }
    });

    function createWindow() {
        let windowIcon;
        const fs = require("fs");

        // Prioridad: icon16x16.ico > icon.ico > icon.png (macOS)
        if (process.platform === "darwin") {
            // macOS: intentar PNG primero
            const pngIconPath = path.join(__dirname, "renderer", "icon.png");
            if (fs.existsSync(pngIconPath)) {
                windowIcon = pngIconPath;
            }
        } else {
            // Windows: buscar icono en múltiples ubicaciones (desarrollo y compilado)
            // Prioridad: icon.ico (con múltiples tamaños) > icon16x16.ico
            const possibleIconPaths = [
                path.join(__dirname, "build", "icon.ico"), // Prioridad: icon.ico completo
                path.join(__dirname, "build", "icon16x16.ico"),
                // Rutas cuando está compilado
                path.join(
                    process.resourcesPath || __dirname,
                    "build",
                    "icon.ico"
                ),
                path.join(
                    process.resourcesPath || __dirname,
                    "build",
                    "icon16x16.ico"
                ),
                // Ruta relativa desde recursos
                path.join(app.getAppPath(), "build", "icon.ico"),
                path.join(app.getAppPath(), "build", "icon16x16.ico"),
            ];

            for (const iconPath of possibleIconPaths) {
                if (iconPath && fs.existsSync(iconPath)) {
                    windowIcon = iconPath;
                    console.log(`✅ Icono encontrado: ${iconPath}`);
                    break;
                }
            }

            // Si está compilado y no encontramos el archivo, el icono debería estar embebido
            if (!windowIcon && app.isPackaged) {
                // En Windows compilado, electron-builder embebe el icono en el ejecutable
                // No necesitamos establecerlo manualmente
                console.log(
                    "ℹ️ Usando icono embebido del ejecutable (compilado)"
                );
            }
        }

        mainWindow = new BrowserWindow({
            width: 800,
            height: 600,
            webPreferences: {
                preload: path.join(__dirname, "preload.js"),
                nodeIntegration: false,
                contextIsolation: true,
            },
            // Solo establecer icono en desarrollo, en compilado está embebido
            ...(windowIcon && !app.isPackaged && { icon: windowIcon }),
            show: false, // Se mostrará cuando esté lista
            skipTaskbar: false, // Asegurar que aparezca en la barra de tareas de Windows
            autoHideMenuBar: true, // Ocultar barra de menú en Windows
        });

        // En Windows, asegurar que el icono se muestre correctamente en la barra de tareas
        if (process.platform === "win32") {
            // En Windows compilado, electron-builder embebe el icono en el ejecutable
            // No necesitamos establecerlo manualmente si está compilado
            // Solo establecerlo si estamos en desarrollo o si encontramos el archivo

            // Intentar múltiples rutas para el icono (desarrollo vs compilado)
            let finalIcon = windowIcon;

            // Si no encontramos el icono, intentar rutas alternativas
            if (!finalIcon || !fs.existsSync(finalIcon)) {
                const possiblePaths = [
                    path.join(__dirname, "build", "icon.ico"), // Prioridad: icon.ico completo
                    path.join(__dirname, "build", "icon16x16.ico"),
                    path.join(
                        process.resourcesPath || __dirname,
                        "build",
                        "icon.ico"
                    ),
                    path.join(
                        process.resourcesPath || __dirname,
                        "build",
                        "icon16x16.ico"
                    ),
                ];

                for (const iconPath of possiblePaths) {
                    if (fs.existsSync(iconPath)) {
                        finalIcon = iconPath;
                        console.log(`✅ Icono encontrado en: ${iconPath}`);
                        break;
                    }
                }
            }

            // En desarrollo, establecer el icono manualmente
            // En compilado, el icono ya está embebido en el ejecutable por electron-builder
            // NO establecerlo manualmente en compilado para que Windows use el icono embebido
            if (!app.isPackaged && finalIcon && fs.existsSync(finalIcon)) {
                // Solo en desarrollo: establecer el icono manualmente
                mainWindow.setIcon(finalIcon);
                sendLogToUI(
                    `✅ Icono configurado (desarrollo): ${path.basename(
                        finalIcon
                    )}`,
                    "success"
                );
            } else if (app.isPackaged) {
                // Si está compilado, el icono está embebido en el ejecutable
                // electron-builder lo maneja automáticamente
                // NO llamar setIcon() para que Windows use el icono embebido del ejecutable
                sendLogToUI(
                    "ℹ️ Usando icono embebido del ejecutable (compilado)",
                    "info"
                );
            } else {
                sendLogToUI(
                    "⚠️ No se encontró icono, usando icono por defecto",
                    "warning"
                );
            }

            // Forzar que aparezca en la barra de tareas
            mainWindow.setSkipTaskbar(false);

            // En desarrollo, también configurar el icono después de que la ventana esté lista
            if (!app.isPackaged) {
                mainWindow.once("ready-to-show", () => {
                    if (finalIcon && fs.existsSync(finalIcon)) {
                        mainWindow.setIcon(finalIcon);
                    }
                });
            }
            // En compilado, no hacer nada - el icono embebido se usará automáticamente
        }

        mainWindow.loadFile("renderer/index.html");

        // Esperar a que el DOM esté completamente cargado antes de enviar logs
        mainWindow.webContents.once("did-finish-load", () => {
            // Marcar ventana como lista para recibir logs
            isWindowReady = true;

            // Enviar un log de prueba inmediatamente para verificar que funciona
            setTimeout(() => {
                try {
                    mainWindow.webContents.send("app-log", {
                        message: "✅ Ventana lista - Sistema de logs activo",
                        type: "success",
                    });
                } catch (error) {
                    console.error(
                        "Error enviando log de prueba:",
                        error.message
                    );
                }

                // Enviar todos los logs del buffer
                if (logBuffer.length > 0) {
                    console.log(
                        `📤 Enviando ${logBuffer.length} logs del buffer...`
                    );
                    logBuffer.forEach((log) => {
                        try {
                            mainWindow.webContents.send("app-log", {
                                message: log.message,
                                type: log.type,
                            });
                        } catch (error) {
                            console.error(
                                "Error enviando log del buffer:",
                                error.message
                            );
                        }
                    });
                    logBuffer = []; // Limpiar buffer
                }
            }, 800); // Esperar 800ms para asegurar que el listener esté completamente listo
        });

        // Mostrar ventana cuando esté lista
        mainWindow.once("ready-to-show", () => {
            const config = configManager?.getConfig();
            const startMinimized = config?.startMinimized !== false;
            const isHiddenArg = process.argv.includes("--hidden");

            // En Windows: mostrar siempre (comportamiento normal)
            // En macOS: ocultar si está configurado para iniciar minimizado
            if (process.platform === "win32") {
                // Windows: mostrar siempre para que aparezca en la barra de tareas
                mainWindow.show();
            } else if (process.platform === "darwin") {
                // macOS: solo mostrar si NO está configurado para iniciar minimizado y NO viene con --hidden
                if (!startMinimized && !isHiddenArg) {
                    mainWindow.show();
                }
            }
        });

        // Minimizar a la bandeja del sistema en lugar de cerrar
        mainWindow.on("close", (event) => {
            if (!app.isQuiting) {
                event.preventDefault();
                mainWindow.hide();
                return false;
            }
        });

        // Crear bandeja del sistema
        createTray();
    }

    function createTray() {
        let trayIconPath;
        let trayIconImage = null; // Para almacenar el objeto nativeImage redimensionado en macOS
        const fs = require("fs");

        // En macOS usar PNG redimensionado a 16x16, en Windows usar ICO (prioridad: icon16x16.ico)
        if (process.platform === "darwin") {
            // macOS: buscar icon.png en renderer y redimensionarlo a 16x16 para el system tray
            const pngIconPath = path.join(__dirname, "renderer", "icon.png");
            if (fs.existsSync(pngIconPath)) {
                try {
                    // Redimensionar el icono a 16x16 para macOS system tray (tamaño estándar)
                    const iconImage = nativeImage.createFromPath(pngIconPath);
                    trayIconImage = iconImage.resize({ width: 16, height: 16 });
                    console.log(
                        "✅ Icono redimensionado a 16x16 para macOS system tray"
                    );
                } catch (error) {
                    console.log(
                        "⚠️ Error redimensionando icono, usando original:",
                        error.message
                    );
                    trayIconPath = pngIconPath;
                }
            } else {
                // Usar icono por defecto de Electron en macOS
                const electronPath = require("electron").app.getAppPath();
                trayIconPath = path.join(
                    electronPath,
                    "node_modules",
                    "electron",
                    "dist",
                    "Electron.app",
                    "Contents",
                    "Resources",
                    "electron.icns"
                );
                if (!fs.existsSync(trayIconPath)) {
                    trayIconPath = undefined; // Electron manejará el icono por defecto
                }
            }
        } else {
            // Windows: usar icon16x16.ico como primera opción para el system tray (barra de tareas)
            const icon16Path = path.join(__dirname, "build", "icon16x16.ico");
            const iconPath = path.join(__dirname, "build", "icon.ico");

            if (fs.existsSync(icon16Path)) {
                trayIconPath = icon16Path;
                console.log(
                    "✅ Usando icon16x16.ico para el system tray (barra de tareas)"
                );
            } else if (fs.existsSync(iconPath)) {
                trayIconPath = iconPath;
                console.log(
                    "⚠️ Usando icon.ico como fallback para el system tray"
                );
            } else {
                trayIconPath = undefined;
                console.log(
                    "⚠️ No se encontró icono para el system tray, usando icono por defecto"
                );
            }
        }

        try {
            // En macOS, si tenemos un icono redimensionado (trayIconImage), usarlo directamente
            if (process.platform === "darwin" && trayIconImage) {
                tray = new Tray(trayIconImage);
                console.log(
                    "✅ System tray creado con icono redimensionado (16x16)"
                );
            } else if (trayIconPath && fs.existsSync(trayIconPath)) {
                tray = new Tray(trayIconPath);
                console.log(
                    `✅ System tray creado con icono: ${path.basename(
                        trayIconPath
                    )}`
                );
            } else {
                // Crear icono temporal simple para macOS/Windows
                const img = nativeImage.createEmpty();
                // En macOS, intentar redimensionar el icono del renderer si existe
                if (process.platform === "darwin") {
                    const rendererIcon = path.join(
                        __dirname,
                        "renderer",
                        "icon.png"
                    );
                    if (fs.existsSync(rendererIcon)) {
                        try {
                            const iconImage =
                                nativeImage.createFromPath(rendererIcon);
                            const resizedIcon = iconImage.resize({
                                width: 16,
                                height: 16,
                            });
                            tray = new Tray(resizedIcon);
                            console.log(
                                "✅ System tray creado con icono redimensionado desde renderer"
                            );
                        } catch (e) {
                            tray = new Tray(img);
                        }
                    } else {
                        // Icono por defecto de Electron
                        tray = new Tray(img);
                    }
                } else {
                    // Windows: icono por defecto
                    tray = new Tray(img);
                }
            }
        } catch (error) {
            console.log(
                "⚠️ Error cargando icono del system tray, usando icono por defecto:",
                error.message
            );
            // Crear icono vacío como fallback
            tray = new Tray(nativeImage.createEmpty());
        }

        const contextMenu = Menu.buildFromTemplate([
            {
                label: "Abrir",
                click: () => {
                    if (mainWindow) {
                        mainWindow.show();
                        mainWindow.focus();
                    }
                },
            },
            {
                label: "Estado: Desconectado",
                id: "status",
                enabled: false,
            },
            { type: "separator" },
            {
                label: "Reconectar WebSocket",
                click: () => {
                    if (websocketService) {
                        websocketService.disconnect();
                        setTimeout(() => {
                            websocketService.connect();
                        }, 1000);
                    }
                },
            },
            { type: "separator" },
            {
                label: "Salir",
                click: () => {
                    app.isQuiting = true;
                    if (websocketService) {
                        websocketService.disconnect();
                    }
                    app.quit();
                },
            },
        ]);

        tray.setToolTip("GridPOS Printer");
        tray.setContextMenu(contextMenu);

        // Actualizar estado periódicamente
        setInterval(() => {
            const isConnected = websocketService?.isConnected() || false;
            const statusItem = contextMenu.getMenuItemById("status");
            if (statusItem) {
                statusItem.label = `Estado: ${
                    isConnected ? "Conectado" : "Desconectado"
                }`;
            }
        }, 1000);
    }

    app.whenReady().then(() => {
        // En Windows, configurar el App User Model ID para que el icono aparezca correctamente en la barra de tareas
        if (process.platform === "win32") {
            app.setAppUserModelId("co.gridpos.printer");
            sendLogToUI("App User Model ID configurado para Windows", "info");
        }

        sendLogToUI("Inicializando servicios...", "info");
        configManager = new ConfigManager();
        printerService = new PrinterService();
        websocketService = new WebSocketService(configManager, printerService);
        sendLogToUI("Servicios inicializados correctamente", "success");

        // Configurar auto-inicio en Windows
        const autoStart = setupAutoStart();
        if (autoStart) {
            const config = configManager.getConfig();
            if (config.autoStart !== false) {
                autoStart.enable();
                sendLogToUI("Auto-inicio habilitado en Windows", "success");
            } else {
                sendLogToUI("Auto-inicio deshabilitado", "info");
            }
        }

        // Crear ventana
        sendLogToUI("Creando ventana principal...", "info");
        createWindow();

        // En macOS: ocultar ventana al inicio si está configurado para iniciar minimizado
        // En Windows: siempre mostrar (comportamiento normal)
        if (process.platform === "darwin") {
            const startMinimized = config.startMinimized !== false; // Por defecto iniciar minimizado
            const isHiddenArg = process.argv.includes("--hidden");

            if ((startMinimized || isHiddenArg) && mainWindow) {
                mainWindow.hide();
                console.log(
                    "🔇 Aplicación iniciada en segundo plano (system tray) - macOS"
                );
            }
        } else {
            // Windows: la ventana se mostrará cuando esté lista (en ready-to-show)
            console.log("🪟 Aplicación iniciada normalmente - Windows");
        }

        // Iniciar conexión WebSocket siempre (se reconectará automáticamente)
        // Esperar un momento para asegurar que todo esté inicializado
        setTimeout(() => {
            const currentConfig = configManager.getConfig();
            if (
                currentConfig.apiKey &&
                currentConfig.userId &&
                currentConfig.businessId
            ) {
                sendLogToUI("🚀 Iniciando conexión WebSocket...", "info");
                sendLogToUI(`   User ID: ${currentConfig.userId}`, "debug");
                sendLogToUI(
                    `   Business ID: ${currentConfig.businessId}`,
                    "debug"
                );
                websocketService.connect().catch((error) => {
                    sendLogToUI(
                        `❌ Error conectando WebSocket: ${error.message}`,
                        "error"
                    );
                });
            } else {
                // Si no hay configuración, intentar conectar cada 10 segundos hasta que se configure
                sendLogToUI("⚠️ Esperando configuración...", "warning");
                sendLogToUI(
                    `   API Key: ${
                        currentConfig.apiKey
                            ? "✅ Configurado"
                            : "❌ NO CONFIGURADO"
                    }`,
                    "debug"
                );
                sendLogToUI(
                    `   User ID: ${
                        currentConfig.userId || "❌ NO CONFIGURADO"
                    }`,
                    "debug"
                );
                sendLogToUI(
                    `   Business ID: ${
                        currentConfig.businessId || "❌ NO CONFIGURADO"
                    }`,
                    "debug"
                );
                const checkConfigInterval = setInterval(() => {
                    const checkConfig = configManager.getConfig();
                    if (
                        checkConfig.apiKey &&
                        checkConfig.userId &&
                        checkConfig.businessId
                    ) {
                        clearInterval(checkConfigInterval);
                        sendLogToUI(
                            "✅ Configuración encontrada, conectando...",
                            "success"
                        );
                        websocketService.connect().catch((error) => {
                            sendLogToUI(
                                `❌ Error conectando: ${error.message}`,
                                "error"
                            );
                        });
                    }
                }, 10000);
            }
        }, 1000); // Esperar 1 segundo para asegurar inicialización completa

        // Mantener conexión activa - reconectar si se pierde
        setInterval(() => {
            if (
                !websocketService.isConnected() &&
                !websocketService.getIsConnecting()
            ) {
                const currentConfig = configManager.getConfig();
                if (
                    currentConfig.apiKey &&
                    currentConfig.userId &&
                    currentConfig.businessId
                ) {
                    sendLogToUI(
                        "🔄 Verificando conexión... reconectando si es necesario",
                        "info"
                    );
                    websocketService.connect().catch((error) => {
                        sendLogToUI(
                            `❌ Error en reconexión automática: ${error.message}`,
                            "error"
                        );
                    });
                }
            }
        }, 30000); // Verificar cada 30 segundos

        app.on("activate", () => {
            if (BrowserWindow.getAllWindows().length === 0) {
                createWindow();
            } else if (mainWindow) {
                mainWindow.show();
            }
        });
    });

    app.on("window-all-closed", () => {
        if (process.platform !== "darwin") {
            // No hacer nada, mantener la app corriendo
        }
    });

    app.on("before-quit", () => {
        app.isQuiting = true;
        if (websocketService) {
            websocketService.disconnect();
        }
    });

    // IPC Handlers
    ipcMain.handle("get-config", () => {
        return configManager.getConfig();
    });

    ipcMain.handle("save-config", (event, config) => {
        sendLogToUI("💾 Guardando configuración...", "info");
        configManager.saveConfig(config);
        sendLogToUI("✅ Configuración guardada correctamente", "success");

        // Habilitar auto-start si está configurado
        const autoStart = setupAutoStart();
        if (autoStart) {
            const savedConfig = configManager.getConfig();
            if (savedConfig.autoStart !== false) {
                sendLogToUI(
                    "✅ Habilitando auto-inicio con Windows...",
                    "success"
                );
                autoStart.enable();
            } else {
                sendLogToUI("⚠️ Deshabilitando auto-inicio...", "info");
                autoStart.disable();
            }
        }

        // Reconectar WebSocket con nueva configuración
        if (websocketService) {
            sendLogToUI(
                "🔄 Reconectando WebSocket con nueva configuración...",
                "info"
            );
            websocketService.disconnect();
            setTimeout(() => {
                websocketService.connect().catch((error) => {
                    sendLogToUI(
                        `❌ Error reconectando: ${error.message}`,
                        "error"
                    );
                });
            }, 1000);
        }
        return { success: true };
    });

    ipcMain.handle("get-status", () => {
        return {
            connected: websocketService?.isConnected() || false,
            connecting: websocketService?.getIsConnecting() || false,
            lastMessage: websocketService?.getLastMessageTime() || null,
        };
    });

    ipcMain.handle("test-connection", async () => {
        try {
            await websocketService.testConnection();
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    });

    ipcMain.handle("connect-websocket", async () => {
        try {
            if (
                !websocketService.isConnected() &&
                !websocketService.getIsConnecting()
            ) {
                sendLogToUI("🔄 Conectando WebSocket manualmente...", "info");
                websocketService.connect().catch((error) => {
                    sendLogToUI(
                        `❌ Error conectando manualmente: ${error.message}`,
                        "error"
                    );
                });
                return { success: true };
            } else if (websocketService.isConnected()) {
                sendLogToUI("ℹ️ Ya está conectado", "info");
                return { success: true, message: "Ya está conectado" };
            } else {
                sendLogToUI("ℹ️ Ya está intentando conectar...", "info");
                return {
                    success: true,
                    message: "Ya está intentando conectar",
                };
            }
        } catch (error) {
            sendLogToUI(`❌ Error: ${error.message}`, "error");
            return { success: false, error: error.message };
        }
    });

    ipcMain.handle("disconnect-websocket", async () => {
        try {
            if (
                websocketService.isConnected() ||
                websocketService.getIsConnecting()
            ) {
                console.log("⚠️ Desconectando WebSocket manualmente...");
                websocketService.disconnect();
                return { success: true };
            } else {
                return { success: true, message: "Ya está desconectado" };
            }
        } catch (error) {
            return { success: false, error: error.message };
        }
    });

    ipcMain.handle("get-printers", async () => {
        return await printerService.getAvailablePrinters();
    });

    ipcMain.handle("test-printer", async (event, printerName) => {
        try {
            await printerService.testPrint(printerName);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    });

    // Escuchar eventos de estado del WebSocket
    websocketService?.on("connected", () => {
        sendLogToUI("✅ WebSocket conectado exitosamente", "success");
        if (mainWindow) {
            mainWindow.webContents.send("websocket-status", {
                connected: true,
            });
        }
    });

    websocketService?.on("disconnected", () => {
        sendLogToUI("⚠️ WebSocket desconectado", "warning");
        if (mainWindow) {
            mainWindow.webContents.send("websocket-status", {
                connected: false,
            });
        }
    });

    websocketService?.on("error", (error) => {
        sendLogToUI(`❌ Error WebSocket: ${error.message}`, "error");
        if (mainWindow) {
            mainWindow.webContents.send("websocket-error", {
                error: error.message,
            });
        }
    });

    // Escuchar eventos de log del WebSocketService
    websocketService?.on("log", (logData) => {
        if (logData && logData.message) {
            sendLogToUI(logData.message, logData.type || "info");
        }
    });

    // Escuchar eventos de log de printerService
    printerService?.on("log", (logData) => {
        if (logData && logData.message) {
            sendLogToUI(logData.message, logData.type || "info");
        }
    });
}
