const Store = require("electron-store");

class ConfigManager {
    constructor() {
        this.store = new Store({
            name: "gridpos-printer-config",
            defaults: {
                apiKey: "your-secure-api-key-for-laravel-communication",
                userId: "",
                businessId: "",
                role: "user",
                wsUrl: "wss://ws.gridpos.co",
                authUrl: "https://ws.gridpos.co/api/auth/token",
                defaultPrinter: "POS-80",
                autoConnect: true,
                autoStart: true, // Iniciar automáticamente con Windows
                startMinimized: true, // Iniciar minimizado en segundo plano
                retryDelay: 3, // Delay inicial de 3 segundos
                maxRetries: 0, // 0 = infinito (reintentar siempre hasta conectar)
            },
        });
    }

    getConfig() {
        const config = this.store.store;
        
        // Migración: corregir businessId si contiene el valor antiguo
        if (config.businessId && config.businessId.includes('-server-print') && !config.businessId.includes('-server-printer')) {
            config.businessId = config.businessId.replace('-server-print', '-server-printer');
            this.store.set('businessId', config.businessId);
            console.log(`✅ Migración: businessId actualizado de "${config.businessId.replace('-server-printer', '-server-print')}" a "${config.businessId}"`);
        }
        
        // Si tiene userId pero businessId está vacío o tiene el formato antiguo, regenerarlo
        if (config.userId && (!config.businessId || config.businessId.includes('-server-print'))) {
            config.businessId = `${config.userId}-server-printer`;
            this.store.set('businessId', config.businessId);
        }
        
        return config;
    }

    saveConfig(config) {
        // Validar configuración requerida
        if (!config.userId) {
            throw new Error("User ID es requerido");
        }

        // Migración: corregir businessId si contiene el valor antiguo
        if (config.businessId && config.businessId.includes('-server-print') && !config.businessId.includes('-server-printer')) {
            config.businessId = config.businessId.replace('-server-print', '-server-printer');
        }

        // Generar Business ID automáticamente si no se proporciona
        if (!config.businessId && config.userId) {
            config.businessId = `${config.userId}-server-printer`;
        }

        // Asegurar que el API Key siempre esté configurado
        if (!config.apiKey) {
            config.apiKey = "your-secure-api-key-for-laravel-communication";
        }

        // Asegurar que el rol tenga un valor por defecto
        if (!config.role) {
            config.role = "user";
        }

        // Guardar configuración
        Object.keys(config).forEach((key) => {
            if (config[key] !== undefined) {
                this.store.set(key, config[key]);
            }
        });

        return true;
    }

    get(key) {
        return this.store.get(key);
    }

    set(key, value) {
        return this.store.set(key, value);
    }
}

module.exports = ConfigManager;
