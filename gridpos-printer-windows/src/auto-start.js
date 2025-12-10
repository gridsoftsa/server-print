const { app } = require("electron");
const { exec } = require("child_process");
const path = require("path");

/**
 * Configurar auto-inicio en Windows
 */
function setupAutoStart() {
    if (process.platform !== "win32") {
        // Retornar objeto dummy con métodos vacíos para otras plataformas
        return {
            enable: () => {
                console.log("⚠️ Auto-inicio solo disponible en Windows");
            },
            disable: () => {
                console.log("⚠️ Auto-inicio solo disponible en Windows");
            },
            isEnabled: (callback) => {
                if (callback) callback(false);
            },
        };
    }

    const appPath = process.execPath;
    const appName = app.getName();
    const regPath = `HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run`;
    const regKey = appName.replace(/\s+/g, "");
    const regValue = `"${appPath}" --hidden`;

    // Usar reg.exe para agregar/remover del registro de Windows
    const addToStartup = () => {
        exec(
            `reg add "${regPath}" /v "${regKey}" /t REG_SZ /d ${regValue} /f`,
            (error) => {
                if (error) {
                    console.error(
                        "Error agregando al inicio automático:",
                        error
                    );
                } else {
                    console.log("✅ Agregado al inicio automático de Windows");
                }
            }
        );
    };

    const removeFromStartup = () => {
        exec(`reg delete "${regPath}" /v "${regKey}" /f`, (error) => {
            if (error) {
                console.error("Error removiendo del inicio automático:", error);
            } else {
                console.log("✅ Removido del inicio automático de Windows");
            }
        });
    };

    return {
        enable: addToStartup,
        disable: removeFromStartup,
        isEnabled: (callback) => {
            exec(`reg query "${regPath}" /v "${regKey}"`, (error) => {
                callback(!error);
            });
        },
    };
}

module.exports = setupAutoStart;
