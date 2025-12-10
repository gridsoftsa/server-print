const WebSocket = require("ws");
const axios = require("axios");
const EventEmitter = require("events");

class WebSocketService extends EventEmitter {
    constructor(configManager, printerService) {
        super();
        this.configManager = configManager;
        this.printerService = printerService;
        this.ws = null;
        this.reconnectTimer = null;
        this.pingTimer = null;
        this.connectionCheckTimer = null; // Timer para verificación periódica
        this.isConnecting = false;
        this.shouldReconnect = true;
        this.retryCount = 0;
        this.lastMessageTime = null;
        this.maxRetries = 0; // 0 = infinito
        this.connectionTimeout = null;
        this.reconnectDelay = 3000; // Delay inicial de 3 segundos (más agresivo)
        this.maxReconnectDelay = 60000; // Máximo 1 minuto (más agresivo)
        this.consecutive502Errors = 0; // Contador de errores 502 consecutivos
        this.wasConnected = false; // Flag para saber si alguna vez se conectó exitosamente
        this.isSocketIO = false; // Flag para saber si estamos usando Socket.IO
        this.handshakeCompleted = false; // Flag para saber si el handshake está completo
    }

    async connect() {
        // Si ya está conectado, no hacer nada
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return;
        }

        // Si ya está intentando conectar, esperar
        if (this.isConnecting) {
            return;
        }

        const config = this.configManager.getConfig();

        if (!config.apiKey || !config.userId || !config.businessId) {
            const logMessage =
                "⚠️ Configuración incompleta, esperando configuración...";
            console.log(logMessage);
            this.emit("log", { message: logMessage, type: "warning" });
            // Intentar reconectar después de un delay si falta configuración
            if (this.shouldReconnect) {
                setTimeout(() => this.connect(), 10000); // Reintentar cada 10 segundos
            }
            return;
        }

        this.isConnecting = true;
        this.shouldReconnect = true;

        try {
            const logMessage = `🔄 Intentando conectar WebSocket (intento ${
                this.retryCount + 1
            })...`;
            console.log(logMessage);
            this.emit("log", { message: logMessage, type: "info" });

            // 1. Autenticar y obtener token
            const token = await this.authenticate(config);

            if (!token) {
                const errorMsg = "No se pudo obtener el token de autenticación";
                this.emit("log", { message: `❌ ${errorMsg}`, type: "error" });
                throw new Error(errorMsg);
            }

            // 2. Conectar WebSocket
            await this.connectWebSocket(config.wsUrl, token, config);

            // Resetear contador de reintentos al conectar exitosamente
            this.retryCount = 0;
            this.reconnectDelay = 3000; // Resetear delay a 3 segundos
            this.consecutive502Errors = 0; // Resetear contador de errores 502
            this.isConnecting = false;

            // Iniciar verificación periódica de conexión
            this.startConnectionCheck();
        } catch (error) {
            this.isConnecting = false;
            const errorMsg = `❌ Error conectando: ${error.message}`;
            console.error(errorMsg);
            this.emit("log", { message: errorMsg, type: "error" });
            this.emit("error", error);

            // Intentar reconexión automática SIEMPRE (como en Laravel)
            if (this.shouldReconnect) {
                // Detectar errores 502
                const is502Error =
                    error.message.includes("502") ||
                    error.message.includes("Bad Gateway") ||
                    error.message.includes("Unexpected server response: 502");

                if (is502Error) {
                    this.consecutive502Errors++;
                    const logMessage = `🔄 Error 502 detectado (consecutivo #${this.consecutive502Errors}), reintentando forzosamente...`;
                    console.log(logMessage);
                    this.emit("log", { message: logMessage, type: "warning" });

                    // Resetear flag de conexión para permitir reintento inmediato
                    this.isConnecting = false;

                    // Reintentar inmediatamente con delay corto (1-3 segundos)
                    const retryDelay = Math.min(
                        1000 * this.consecutive502Errors,
                        3000
                    );
                    setTimeout(() => {
                        if (this.shouldReconnect) {
                            // Forzar reset del flag antes de reconectar
                            this.isConnecting = false;
                            this.connect().catch((err) => {
                                // Manejar errores sin causar unhandled rejection
                                console.error(
                                    "Error en reconexión forzosa:",
                                    err.message
                                );
                            });
                        }
                    }, retryDelay);
                } else if (
                    error.message.includes("Timeout") ||
                    error.message.includes("cerrado") ||
                    error.message.includes("Todos los métodos")
                ) {
                    // Para otros errores de servidor, usar schedule normal
                    this.consecutive502Errors = 0; // Resetear contador si no es 502
                    this.scheduleReconnect(config);
                } else {
                    // Para otros errores, usar el schedule normal
                    this.consecutive502Errors = 0; // Resetear contador
                    this.scheduleReconnect(config);
                }
            }
        }
    }

    async authenticate(config) {
        try {
            // Log de credenciales usadas (sin mostrar valores completos por seguridad)
            const authLog = `🔐 Autenticando con User ID: ${
                config.userId || "NO CONFIGURADO"
            }, Business ID: ${config.businessId || "NO CONFIGURADO"}`;
            console.log(authLog);
            this.emit("log", { message: authLog, type: "info" });

            // Logs detallados solo en consola
            console.log(`   - API Key: ${config.apiKey}`);
            console.log(`   - User ID: ${config.userId || "NO CONFIGURADO"}`);
            console.log(
                `   - Business ID: ${config.businessId || "NO CONFIGURADO"}`
            );
            console.log(`   - Role: ${config.role || "user"}`);
            console.log(
                `   - Auth URL: ${
                    config.authUrl || "https://ws.gridpos.co/api/auth/token"
                }`
            );

            const response = await axios.post(
                config.authUrl || "https://ws.gridpos.co/api/auth/token",
                {
                    userId: config.userId,
                    businessId: config.businessId,
                    role: config.role || "user",
                },
                {
                    headers: {
                        "X-API-Key": config.apiKey,
                        "Content-Type": "application/json",
                    },
                    timeout: 15000, // Aumentar timeout a 15 segundos
                }
            );

            if (response.status === 200 && response.data.token) {
                const successMsg = "✅ Token obtenido exitosamente";
                console.log(successMsg);
                this.emit("log", { message: successMsg, type: "success" });

                // Log detallado solo en consola
                console.log(
                    `🔑 Token: ${response.data.token.substring(
                        0,
                        20
                    )}...${response.data.token.substring(
                        response.data.token.length - 10
                    )}`
                );
                return response.data.token;
            }

            throw new Error("Respuesta de autenticación inválida");
        } catch (error) {
            if (error.response) {
                console.error(
                    `❌ Error de autenticación HTTP: ${error.response.status} - ${error.response.statusText}`
                );
                throw new Error(
                    `Error de autenticación: ${error.response.status} - ${error.response.statusText}`
                );
            }
            console.error(`❌ Error de conexión: ${error.message}`);
            throw new Error(`Error de conexión: ${error.message}`);
        }
    }

    async connectWebSocket(wsUrl, token, config) {
        return new Promise((resolve, reject) => {
            // Intentar múltiples formatos de URL como en Laravel
            // 1. Primero intentar con token en query string + header
            const url1 = `${wsUrl}?token=${encodeURIComponent(token)}`;

            // 2. Fallback: solo con token en query string
            const url2 = `${wsUrl}?token=${encodeURIComponent(token)}`;

            // 3. Fallback: Socket.IO Engine.IO format
            const base = wsUrl.replace(/\/$/, "");
            const url3 = `${base}/socket.io/?EIO=4&transport=websocket&token=${encodeURIComponent(
                token
            )}`;

            let attempt = 0;
            const urls = [url1, url2, url3];

            const tryConnect = (url, useHeaders = true) => {
                attempt++;
                const usingSocketIO = url.includes("/socket.io/");
                this.isSocketIO = usingSocketIO;
                this.handshakeCompleted = false; // Reset handshake flag para cada intento
                console.log(
                    `🔌 Intentando conexión (método ${attempt}/3)${
                        usingSocketIO ? " [Socket.IO]" : ""
                    }...`
                );

                // Limpiar conexión anterior si existe y está cerrada o cerrando
                if (this.ws) {
                    try {
                        const currentState = this.ws.readyState;
                        // Solo cerrar si no está en proceso de conexión y no está ya cerrada
                        if (
                            currentState !== WebSocket.CLOSED &&
                            currentState !== WebSocket.CLOSING
                        ) {
                            // Remover listeners primero para evitar eventos durante el cierre
                            this.ws.removeAllListeners();

                            // Si está conectado o conectando, usar terminate para forzar cierre limpio
                            if (
                                currentState === WebSocket.OPEN ||
                                currentState === WebSocket.CONNECTING
                            ) {
                                try {
                                    // Verificar que el WebSocket aún existe y no está cerrado antes de terminate
                                    if (
                                        this.ws &&
                                        this.ws.readyState !==
                                            WebSocket.CLOSED &&
                                        this.ws.readyState !== WebSocket.CLOSING
                                    ) {
                                        this.ws.terminate();
                                    }
                                } catch (terminateError) {
                                    // Si terminate falla, intentar close como fallback
                                    try {
                                        if (
                                            this.ws &&
                                            this.ws.readyState !==
                                                WebSocket.CLOSED
                                        ) {
                                            this.ws.close();
                                        }
                                    } catch (closeError) {
                                        // Ignorar errores al cerrar
                                    }
                                }
                            } else {
                                try {
                                    if (
                                        this.ws &&
                                        this.ws.readyState !== WebSocket.CLOSED
                                    ) {
                                        this.ws.close();
                                    }
                                } catch (closeError) {
                                    // Ignorar errores al cerrar
                                }
                            }
                        } else {
                            // Solo remover listeners si ya está cerrada
                            this.ws.removeAllListeners();
                        }
                    } catch (e) {
                        // Ignorar errores al limpiar conexión anterior
                        console.log(
                            "⚠️ Error limpiando conexión anterior (ignorado):",
                            e.message
                        );
                    } finally {
                        // Asegurar que la referencia se limpia
                        this.ws = null;
                    }
                }

                const wsOptions = useHeaders
                    ? {
                          headers: {
                              Authorization: `Bearer ${token}`,
                          },
                      }
                    : {};

                this.ws = new WebSocket(url, wsOptions);

                // Variable para rastrear el timeout y evitar unhandled rejection
                let connectionTimeout = null;
                let isResolved = false;

                this.ws.on("open", () => {
                    // Limpiar timeout si existe
                    if (connectionTimeout) {
                        clearTimeout(connectionTimeout);
                        connectionTimeout = null;
                    }

                    if (isResolved) return; // Evitar resolver dos veces
                    isResolved = true;

                    const successMsg = `✅ WebSocket conectado exitosamente (método ${attempt})`;
                    console.log(successMsg);
                    this.emit("log", { message: successMsg, type: "success" });
                    this.retryCount = 0;
                    this.reconnectDelay = 3000; // Resetear delay a 3 segundos
                    this.consecutive502Errors = 0; // Resetear contador de errores 502
                    this.lastMessageTime = new Date();
                    this.wasConnected = true; // Marcar que se conectó exitosamente
                    this.emit("connected");

                    // Si es Socket.IO, esperar un momento antes de enviar el packet de conexión
                    if (url.includes("/socket.io/")) {
                        // Esperar un poco para asegurar que la conexión está estable
                        setTimeout(() => {
                            try {
                                if (
                                    this.ws &&
                                    this.ws.readyState === WebSocket.OPEN
                                ) {
                                    // Socket.IO Engine.IO v4: "40" es el packet de conexión para el namespace "/"
                                    // El formato correcto es "40" seguido del payload JSON si hay datos
                                    // Para el namespace por defecto, solo "40" es suficiente
                                    // Pero si necesitamos enviar el token, lo enviamos como "40" + JSON payload
                                    const payload = JSON.stringify({
                                        token: token,
                                    });
                                    this.ws.send("40" + payload); // Socket.IO connect packet
                                    this.handshakeCompleted = true; // Marcar handshake como completado
                                    const logMsg =
                                        "📤 Enviado packet Socket.IO de conexión (handshake completado)";
                                    console.log(logMsg);
                                    this.emit("log", {
                                        message: logMsg,
                                        type: "info",
                                    });
                                }
                            } catch (e) {
                                console.error(
                                    "Error enviando packet Socket.IO:",
                                    e.message
                                );
                            }
                        }, 200); // Reducir a 200ms para responder más rápido
                    } else {
                        // Para conexiones no-Socket.IO, el handshake se considera completado inmediatamente
                        this.handshakeCompleted = true;
                    }

                    // Iniciar ping para mantener la conexión activa
                    this.startPing();

                    resolve();
                });

                this.ws.on("message", (data) => {
                    this.lastMessageTime = new Date();

                    // Log de todos los mensajes recibidos para debugging
                    const messagePreview = data.toString().substring(0, 200);
                    const logMsg = `📨 Mensaje recibido del WebSocket: ${messagePreview}${
                        data.toString().length > 200 ? "..." : ""
                    }`;
                    console.log(logMsg);
                    this.emit("log", { message: logMsg, type: "debug" });

                    this.handleMessage(data);
                });

                this.ws.on("error", (error) => {
                    const errorMsg = error.message || String(error);
                    const logError = `❌ Error WebSocket (método ${attempt}): ${errorMsg}`;
                    console.error(logError);
                    this.emit("log", { message: logError, type: "error" });

                    // Si es error 502, intentar siguiente método inmediatamente
                    if (
                        errorMsg.includes("502") ||
                        errorMsg.includes("Bad Gateway")
                    ) {
                        const log502 =
                            "⚠️ Error 502 detectado, intentando siguiente método...";
                        console.log(log502);
                        this.emit("log", { message: log502, type: "warning" });
                        this.stopPing();

                        // Limpiar timeout
                        if (connectionTimeout) {
                            clearTimeout(connectionTimeout);
                            connectionTimeout = null;
                        }

                        // Cerrar conexión actual de forma segura antes de intentar siguiente método
                        try {
                            if (this.ws) {
                                const currentState = this.ws.readyState;
                                if (
                                    currentState === WebSocket.CONNECTING ||
                                    currentState === WebSocket.OPEN
                                ) {
                                    this.ws.removeAllListeners("error"); // Remover solo el listener de error para evitar loops
                                    try {
                                        if (
                                            this.ws.readyState !==
                                                WebSocket.CLOSED &&
                                            this.ws.readyState !==
                                                WebSocket.CLOSING
                                        ) {
                                            this.ws.terminate();
                                        }
                                    } catch (terminateError) {
                                        // Si terminate falla, intentar close
                                        try {
                                            if (
                                                this.ws.readyState !==
                                                WebSocket.CLOSED
                                            ) {
                                                this.ws.close();
                                            }
                                        } catch (closeError) {
                                            // Ignorar errores al cerrar
                                        }
                                    }
                                }
                            }
                        } catch (e) {
                            // Ignorar errores al cerrar
                        }

                        // Intentar siguiente URL si hay más
                        if (attempt < urls.length) {
                            setTimeout(() => {
                                if (!isResolved && this.shouldReconnect) {
                                    tryConnect(urls[attempt], attempt === 1);
                                }
                            }, 1000); // Esperar 1 segundo antes de siguiente intento
                        } else {
                            // Si ya intentamos todos los métodos, rechazar y dejar que se reintente
                            if (!isResolved) {
                                isResolved = true;
                                this.ws = null;
                                reject(
                                    new Error(
                                        `Error 502: Todos los métodos fallaron`
                                    )
                                );
                            }
                        }
                        return;
                    }

                    this.stopPing();
                    this.emit("error", error);
                });

                this.ws.on("close", (code, reason) => {
                    // Limpiar timeout si existe
                    if (connectionTimeout) {
                        clearTimeout(connectionTimeout);
                        connectionTimeout = null;
                    }

                    const reasonStr = reason ? reason.toString() : "Sin razón";

                    // Si ya se resolvió la conexión exitosamente, manejar como desconexión normal
                    if (isResolved) {
                        const disconnectMsg = `⚠️ WebSocket desconectado: ${code} - ${reasonStr}`;
                        console.log(disconnectMsg);
                        this.emit("log", {
                            message: disconnectMsg,
                            type: "warning",
                        });
                        this.stopPing();
                        this.emit("disconnected");

                        // Limpiar la referencia al WebSocket
                        this.ws = null;

                        // Si la conexión se cerró inmediatamente después de conectar (1005 = No Status)
                        // o código anormal (1006 = Abnormal Closure), intentar reconectar
                        if (code === 1005 || code === 1006) {
                            const reconnectMsg =
                                "🔄 Conexión cerrada anormalmente, programando reconexión...";
                            console.log(reconnectMsg);
                            this.emit("log", {
                                message: reconnectMsg,
                                type: "info",
                            });
                            if (this.shouldReconnect) {
                                this.isConnecting = false;
                                // Esperar un poco antes de reconectar para evitar bucle rápido
                                // Usar scheduleReconnect para mantener consistencia
                                setTimeout(() => {
                                    if (
                                        this.shouldReconnect &&
                                        !this.isConnecting
                                    ) {
                                        const config =
                                            this.configManager.getConfig();
                                        this.scheduleReconnect(config);
                                    }
                                }, 2000); // 2 segundos de delay antes de empezar reconexión
                            }
                        } else {
                            // Para otros códigos de cierre, reconectar normalmente
                            if (this.shouldReconnect) {
                                this.isConnecting = false;
                                const config = this.configManager.getConfig();
                                this.scheduleReconnect(config);
                            }
                        }
                        return;
                    }

                    // Si NO se resolvió, significa que la conexión falló antes de establecerse
                    // Intentar siguiente método solo si no se resolvió
                    if (
                        (code === 1006 ||
                            code === 1002 ||
                            code === 1003 ||
                            code === 1001 ||
                            code === 1000 ||
                            code === 1005) &&
                        !isResolved
                    ) {
                        console.log(
                            `⚠️ WebSocket cerrado antes de establecerse (código ${code}), intentando siguiente método...`
                        );

                        if (attempt < urls.length) {
                            setTimeout(() => {
                                if (!isResolved && this.shouldReconnect) {
                                    tryConnect(urls[attempt], attempt === 1);
                                }
                            }, 1000);
                            return; // No rechazar aún, seguir intentando
                        }
                    }

                    const disconnectErrorMsg = `❌ WebSocket desconectado: ${code} - ${reasonStr}`;
                    console.log(disconnectErrorMsg);
                    this.emit("log", {
                        message: disconnectErrorMsg,
                        type: "error",
                    });

                    this.stopPing();
                    this.emit("disconnected");

                    // Limpiar conexión
                    this.ws = null;

                    // Si no se resolvió la promesa, rechazarla para que el catch maneje la reconexión
                    if (!isResolved) {
                        isResolved = true;
                        reject(
                            new Error(
                                `WebSocket cerrado: ${code} - ${reasonStr}`
                            )
                        );
                    }

                    // Intentar reconexión automática si es necesario
                    if (this.shouldReconnect && !isResolved) {
                        const reconnectMsg =
                            "🔄 Programando reconexión automática...";
                        console.log(reconnectMsg);
                        this.emit("log", {
                            message: reconnectMsg,
                            type: "info",
                        });
                        // Resetear flag para permitir reconexión inmediata
                        this.isConnecting = false;
                        this.scheduleReconnect(config);
                    }
                });

                // Timeout de conexión - más corto para reintentos rápidos
                connectionTimeout = setTimeout(() => {
                    if (
                        this.ws &&
                        this.ws.readyState !== WebSocket.OPEN &&
                        !isResolved
                    ) {
                        const timeoutMsg = `⏱️ Timeout de conexión WebSocket (10s), método ${attempt} falló...`;
                        console.log(timeoutMsg);
                        this.emit("log", {
                            message: timeoutMsg,
                            type: "warning",
                        });

                        // Si hay más métodos por intentar, probar siguiente
                        if (attempt < urls.length) {
                            // Cerrar conexión actual de forma segura
                            try {
                                if (this.ws) {
                                    const currentState = this.ws.readyState;
                                    if (
                                        currentState === WebSocket.CONNECTING ||
                                        currentState === WebSocket.OPEN
                                    ) {
                                        this.ws.removeAllListeners();
                                        this.ws.terminate(); // Usar terminate para conexiones en proceso
                                    } else if (
                                        currentState !== WebSocket.CLOSED &&
                                        currentState !== WebSocket.CLOSING
                                    ) {
                                        this.ws.removeAllListeners();
                                        this.ws.close();
                                    }
                                }
                            } catch (e) {
                                // Ignorar errores al cerrar
                            }

                            setTimeout(() => {
                                if (!isResolved && this.shouldReconnect) {
                                    tryConnect(urls[attempt], attempt === 1);
                                }
                            }, 500);
                        } else {
                            // Si ya intentamos todos, cerrar y rechazar
                            try {
                                if (this.ws) {
                                    const currentState = this.ws.readyState;
                                    if (
                                        currentState === WebSocket.CONNECTING ||
                                        currentState === WebSocket.OPEN
                                    ) {
                                        this.ws.removeAllListeners();
                                        this.ws.terminate();
                                    } else if (
                                        currentState !== WebSocket.CLOSED &&
                                        currentState !== WebSocket.CLOSING
                                    ) {
                                        this.ws.removeAllListeners();
                                        this.ws.close();
                                    }
                                }
                            } catch (e) {
                                // Ignorar errores
                            }

                            if (!isResolved) {
                                isResolved = true;
                                reject(
                                    new Error(
                                        "Timeout: Todos los métodos de conexión fallaron"
                                    )
                                );
                            }
                        }
                    }
                }, 10000); // Timeout de 10 segundos (más corto para reintentos rápidos)
            };

            // Iniciar con el primer método
            tryConnect(urls[0], true);
        });
    }

    handleMessage(data) {
        try {
            const text = data.toString();

            // Manejar diferentes formatos de mensaje
            // Socket.IO Engine.IO ping -> pong
            // IMPORTANTE: Solo responder si estamos usando Socket.IO Y el handshake está completo
            if (text === "2") {
                // Verificar que estamos usando Socket.IO antes de responder
                const currentUrl = this.ws?.url || "";
                const isSocketIOConnection = currentUrl.includes("/socket.io/");

                if (!isSocketIOConnection) {
                    // Si no es Socket.IO, ignorar el ping (podría ser un mensaje normal)
                    const warnLog =
                        "⚠️ Ping recibido pero no estamos usando Socket.IO, ignorando...";
                    console.log(warnLog);
                    this.emit("log", { message: warnLog, type: "warning" });
                    return;
                }

                // Verificar que el handshake esté completo antes de responder
                // Si el handshake no está completo, responder de inmediato de todas formas
                // porque el servidor podría estar esperando el pong para mantener la conexión
                if (!this.handshakeCompleted) {
                    const waitLog =
                        "⏳ Ping recibido antes del handshake, respondiendo de inmediato...";
                    console.log(waitLog);
                    this.emit("log", { message: waitLog, type: "debug" });
                    // Responder inmediatamente para mantener la conexión
                    try {
                        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                            this.ws.send("3");
                            const pongLog =
                                "🏓 Pong enviado (antes de handshake completo)";
                            console.log(pongLog);
                            this.emit("log", {
                                message: pongLog,
                                type: "debug",
                            });
                        }
                    } catch (error) {
                        const errorLog = `❌ Error enviando pong: ${error.message}`;
                        console.error(errorLog);
                        this.emit("log", { message: errorLog, type: "error" });
                    }
                    return;
                }

                // Responder al ping solo si el handshake está completo
                const pingLog = "🏓 Ping recibido, enviando pong...";
                console.log(pingLog);
                this.emit("log", { message: pingLog, type: "debug" });

                try {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send("3");
                    }
                } catch (error) {
                    const errorLog = `❌ Error enviando pong: ${error.message}`;
                    console.error(errorLog);
                    this.emit("log", { message: errorLog, type: "error" });
                }
                return;
            }

            // Socket.IO connect acknowledgment: 40 (sin payload) o 40{...}
            // El servidor confirma que el handshake fue exitoso
            if (text.startsWith("40") && text.length <= 3) {
                const ackLog = `✅ Socket.IO handshake confirmado por servidor`;
                console.log(ackLog);
                this.emit("log", { message: ackLog, type: "success" });
                this.handshakeCompleted = true; // Asegurar que está marcado como completado
                return;
            }

            // Socket.IO event packet: 42["event", {...}]
            if (text.startsWith("42")) {
                const jsonPart = text.substring(2);
                try {
                    const arr = JSON.parse(jsonPart);
                    if (Array.isArray(arr) && arr.length >= 1) {
                        const eventName = arr[0];
                        const eventPayload = arr[1] || null;

                        const eventLog = `📡 Evento Socket.IO recibido: ${eventName}`;
                        console.log(eventLog);
                        this.emit("log", { message: eventLog, type: "info" });

                        if (eventName === "business-event" && eventPayload) {
                            this.processBusinessEvent(eventPayload);
                        } else {
                            const unknownEventLog = `⚠️ Evento desconocido: ${eventName}`;
                            console.log(unknownEventLog);
                            this.emit("log", {
                                message: unknownEventLog,
                                type: "warning",
                            });
                        }
                    }
                } catch (parseError) {
                    const parseErrorLog = `❌ Error parseando mensaje Socket.IO: ${parseError.message}`;
                    console.error(parseErrorLog);
                    this.emit("log", { message: parseErrorLog, type: "error" });
                }
                return;
            }

            // Mensaje JSON directo
            try {
                const message = JSON.parse(text);
                const jsonLog = `📦 Mensaje JSON recibido: ${JSON.stringify(
                    message
                ).substring(0, 100)}...`;
                console.log(jsonLog);
                this.emit("log", { message: jsonLog, type: "info" });

                if (message.action) {
                    this.processDirectMessage(message);
                } else {
                    const noActionLog = `⚠️ Mensaje JSON sin acción: ${JSON.stringify(
                        message
                    ).substring(0, 100)}`;
                    console.log(noActionLog);
                    this.emit("log", { message: noActionLog, type: "warning" });
                }
            } catch (parseError) {
                // Mensaje no parseable como JSON
                const unparseableLog = `⚠️ Mensaje no parseable como JSON: ${text.substring(
                    0,
                    100
                )}`;
                console.log(unparseableLog);
                this.emit("log", { message: unparseableLog, type: "warning" });
            }
        } catch (error) {
            const errorLog = `❌ Error procesando mensaje: ${error.message}`;
            console.error(errorLog);
            this.emit("log", { message: errorLog, type: "error" });
        }
    }

    processBusinessEvent(eventPayload) {
        const data = eventPayload.data || eventPayload;
        const action = data.action || eventPayload.action;

        if (!action) return;

        try {
            switch (action) {
                case "salePrinter":
                    const saleLogMsg = `🖨️ Procesando impresión de venta...`;
                    console.log(saleLogMsg);
                    this.emit("log", { message: saleLogMsg, type: "info" });
                    this.printerService
                        .processSalePrint(data)
                        .catch((error) => {
                            const errorMsg = `❌ Error procesando impresión de venta: ${error.message}`;
                            console.error(errorMsg);
                            this.emit("log", {
                                message: errorMsg,
                                type: "error",
                            });
                        });
                    break;
                case "orderPrinter":
                    const orderLogMsg = `🖨️ Procesando impresión de orden...`;
                    console.log(orderLogMsg);
                    this.emit("log", { message: orderLogMsg, type: "info" });
                    this.printerService
                        .processOrderPrint(data)
                        .catch((error) => {
                            const errorMsg = `❌ Error procesando impresión de orden: ${error.message}`;
                            console.error(errorMsg);
                            this.emit("log", {
                                message: errorMsg,
                                type: "error",
                            });
                        });
                    break;
                case "openCashDrawer":
                    const drawerLogMsg = `💰 Abriendo cajón de efectivo...`;
                    console.log(drawerLogMsg);
                    this.emit("log", { message: drawerLogMsg, type: "info" });
                    const printer =
                        data.printer ||
                        this.configManager.get("defaultPrinter") ||
                        "POS-80";
                    this.printerService
                        .openCashDrawer(printer)
                        .catch((error) => {
                            const errorMsg = `❌ Error abriendo cajón: ${error.message}`;
                            console.error(errorMsg);
                            this.emit("log", {
                                message: errorMsg,
                                type: "error",
                            });
                        });
                    break;
                default:
                    console.log("Acción desconocida:", action);
            }
        } catch (error) {
            console.error(`Error procesando acción ${action}:`, error);
            this.emit("error", error);
        }
    }

    processDirectMessage(message) {
        // Procesar mensajes directos (no Socket.IO)
        const action = message.action;

        try {
            switch (action) {
                case "salePrinter":
                    this.printerService.processSalePrint(message);
                    break;
                case "orderPrinter":
                    this.printerService.processOrderPrint(message);
                    break;
                case "openCashDrawer":
                    const printer =
                        message.printer ||
                        this.configManager.get("defaultPrinter") ||
                        "POS-80";
                    this.printerService.openCashDrawer(printer);
                    break;
            }
        } catch (error) {
            console.error(`Error procesando mensaje directo ${action}:`, error);
        }
    }

    scheduleReconnect(config) {
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }

        // Verificar límite de reintentos (0 = infinito = reintentar siempre)
        if (this.maxRetries > 0 && this.retryCount >= this.maxRetries) {
            const maxRetriesMsg = "❌ Máximo de reintentos alcanzado";
            console.error(maxRetriesMsg);
            this.emit("log", { message: maxRetriesMsg, type: "error" });
            this.shouldReconnect = false;
            return;
        }

        this.retryCount++;

        // Backoff exponencial similar a Laravel (retryDelay * 2^(retryCount-1))
        // Pero más agresivo al inicio para errores 502
        let retryDelay;
        if (this.retryCount <= 3) {
            // Primeros 3 intentos: muy rápido (1s, 2s, 3s) para errores 502
            retryDelay = this.retryCount * 1000;
        } else {
            // Luego: exponencial como Laravel (retryDelay * 2^(n-1))
            retryDelay = Math.min(
                this.reconnectDelay * Math.pow(2, this.retryCount - 1),
                this.maxReconnectDelay
            );
        }

        const reconnectMsg = `⏳ Reintentando conexión en ${Math.round(
            retryDelay / 1000
        )} segundos... (intento ${this.retryCount}, máximo: ${
            this.maxRetries === 0 ? "infinito" : this.maxRetries
        })`;
        console.log(reconnectMsg);
        this.emit("log", { message: reconnectMsg, type: "info" });

        this.reconnectTimer = setTimeout(() => {
            if (this.shouldReconnect) {
                // SIEMPRE resetear flag antes de conectar para permitir reintento forzoso
                this.isConnecting = false;
                const retryMsg =
                    "🔄 Ejecutando reintento forzoso de conexión...";
                console.log(retryMsg);
                this.emit("log", { message: retryMsg, type: "info" });
                this.connect().catch((error) => {
                    // Manejar errores de conexión sin causar unhandled rejection
                    const errorMsg = `Error en reconexión: ${error.message}`;
                    console.error(errorMsg);
                    this.emit("log", { message: errorMsg, type: "error" });
                    // El scheduleReconnect se llamará desde el catch del connect()
                });
            }
        }, retryDelay);
    }

    startPing() {
        // Limpiar ping anterior si existe
        this.stopPing();

        // Enviar ping cada 30 segundos para mantener la conexión activa
        this.pingTimer = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                try {
                    // Enviar ping para Socket.IO Engine.IO (2 = ping)
                    if (this.ws.send) {
                        this.ws.send("2"); // Ping para Socket.IO
                    }
                    // También usar ping nativo si está disponible
                    if (typeof this.ws.ping === "function") {
                        this.ws.ping();
                    }
                } catch (error) {
                    console.error("Error enviando ping:", error.message);
                    // Si hay error, detener ping y reconectar
                    this.stopPing();
                    // Forzar reconexión si hay error en ping
                    if (this.shouldReconnect) {
                        this.isConnecting = false;
                        this.connect().catch(() => {});
                    }
                }
            } else {
                this.stopPing();
            }
        }, 30000); // Cada 30 segundos
    }

    startConnectionCheck() {
        // Limpiar verificación anterior si existe
        this.stopConnectionCheck();

        // Verificar conexión cada 60 segundos y forzar reconexión si está desconectado
        this.connectionCheckTimer = setInterval(() => {
            const config = this.configManager.getConfig();

            // Solo verificar si tenemos configuración completa
            if (!config.apiKey || !config.userId || !config.businessId) {
                return;
            }

            // Si no está conectado y no está intentando conectar, forzar reconexión
            if (
                !this.isConnected() &&
                !this.isConnecting &&
                this.shouldReconnect
            ) {
                console.log(
                    "⚠️ Conexión perdida detectada, forzando reconexión..."
                );
                this.isConnecting = false;
                this.connect().catch((err) => {
                    console.error("Error en reconexión forzada:", err.message);
                });
            }
        }, 60000); // Verificar cada 60 segundos
    }

    stopConnectionCheck() {
        if (this.connectionCheckTimer) {
            clearInterval(this.connectionCheckTimer);
            this.connectionCheckTimer = null;
        }
    }

    stopPing() {
        if (this.pingTimer) {
            clearInterval(this.pingTimer);
            this.pingTimer = null;
        }
    }

    disconnect() {
        this.shouldReconnect = false;
        this.stopPing();
        this.stopConnectionCheck();

        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        if (this.ws) {
            try {
                const currentState = this.ws.readyState;
                // Remover todos los listeners primero para evitar eventos después del cierre
                this.ws.removeAllListeners();

                // Cerrar según el estado actual
                if (
                    currentState === WebSocket.CONNECTING ||
                    currentState === WebSocket.OPEN
                ) {
                    this.ws.terminate(); // Usar terminate para conexiones activas
                } else if (
                    currentState !== WebSocket.CLOSED &&
                    currentState !== WebSocket.CLOSING
                ) {
                    this.ws.close();
                }
            } catch (error) {
                console.error("Error cerrando WebSocket:", error.message);
            }
            this.ws = null;
        }

        this.wasConnected = false;
    }

    isConnected() {
        return this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    getIsConnecting() {
        return this.isConnecting === true;
    }

    getLastMessageTime() {
        return this.lastMessageTime;
    }

    async testConnection() {
        const config = this.configManager.getConfig();

        if (!config.apiKey || !config.userId || !config.businessId) {
            throw new Error("Configuración incompleta");
        }

        try {
            const token = await this.authenticate(config);
            return { success: true, token: token ? "obtenido" : "no obtenido" };
        } catch (error) {
            throw error;
        }
    }
}

module.exports = WebSocketService;
