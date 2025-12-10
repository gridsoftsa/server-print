const {
    ThermalPrinter,
    PrinterTypes,
    CharacterSet,
    BreakLine,
} = require("node-thermal-printer");
const { exec } = require("child_process");
const util = require("util");
const execAsync = util.promisify(exec);
const fs = require("fs").promises;
const path = require("path");
const os = require("os");
const EventEmitter = require("events");

class PrinterService extends EventEmitter {
    constructor() {
        super();
        this.tempDir = path.join(os.tmpdir(), "gridpos-printer");
        this.ensureTempDir();
    }

    // Helper para emitir logs
    emitLog(message, type = "info") {
        console.log(`[${type.toUpperCase()}] ${message}`);
        this.emit("log", { message, type });
    }

    async ensureTempDir() {
        try {
            await fs.mkdir(this.tempDir, { recursive: true });
        } catch (error) {
            console.error("Error creando directorio temporal:", error);
        }
    }

    async getAvailablePrinters() {
        try {
            // Detectar sistema operativo
            const isWindows = process.platform === "win32";
            const isMac = process.platform === "darwin";
            const isLinux = process.platform === "linux";

            if (isWindows) {
                // En Windows, usar PowerShell para listar impresoras (más confiable)
                try {
                    const command =
                        'powershell -Command "Get-Printer | Select-Object -ExpandProperty Name"';
                    const { stdout } = await execAsync(command);
                    const printers = stdout
                        .split("\n")
                        .map((line) => line.trim())
                        .filter((line) => line && line.length > 0)
                        .filter(Boolean);

                    if (printers.length > 0) {
                        return printers;
                    }
                } catch (psError) {
                    console.log(
                        "PowerShell no disponible, intentando con wmic..."
                    );
                }

                // Fallback: usar wmic
                try {
                    const { stdout: wmicStdout } = await execAsync(
                        "wmic printer get name"
                    );
                    const wmicPrinters = wmicStdout
                        .split("\n")
                        .map((line) => line.trim())
                        .filter(
                            (line) =>
                                line &&
                                line !== "Name" &&
                                !line.startsWith("---")
                        )
                        .filter(Boolean);
                    if (wmicPrinters.length > 0) {
                        return wmicPrinters;
                    }
                } catch (wmicError) {
                    console.error(
                        "Error obteniendo impresoras con wmic:",
                        wmicError
                    );
                }
            } else if (isMac || isLinux) {
                // En macOS/Linux, usar lpstat o lpinfo
                try {
                    const command =
                        "lpstat -p 2>/dev/null | awk '{print $2}' || lpinfo -v 2>/dev/null | grep -i printer || echo \"\"";
                    const { stdout } = await execAsync(command, {
                        shell: true,
                    });
                    const printers = stdout
                        .split("\n")
                        .map((line) => line.trim())
                        .filter(
                            (line) =>
                                line &&
                                line.length > 0 &&
                                !line.startsWith("printer")
                        )
                        .filter(Boolean);

                    if (printers.length > 0) {
                        return printers;
                    }
                } catch (lpError) {
                    console.log("lpstat/lpinfo no disponible");
                }
            }

            // Si no se encontraron impresoras, retornar impresora por defecto común
            console.log(
                "⚠️ No se encontraron impresoras, usando impresora por defecto"
            );
            return ["POS-80"];
        } catch (error) {
            console.error("Error obteniendo impresoras:", error.message);
            // Retornar impresora por defecto común
            return ["POS-80"];
        }
    }

    createPrinter(printerName) {
        // Para Windows, usar el nombre de la impresora directamente
        // node-thermal-printer soporta impresoras Windows nativas
        // Manejar impresoras compartidas y locales
        try {
            const isWindows = process.platform === "win32";
            let interfaceStr;
            
            if (isWindows) {
                // Si la impresora está compartida (contiene \\), usar el formato de red directamente
                if (printerName.includes("\\\\") || printerName.startsWith("\\\\")) {
                    // Impresora compartida: usar el formato completo de red
                    interfaceStr = printerName;
                    this.emitLog(`🔗 Detectada impresora compartida: ${printerName}`, "info");
                } else {
                    // Impresora local: usar formato printer:nombre
                    interfaceStr = `printer:${printerName}`;
                }
            } else {
                // macOS/Linux: usar el nombre directamente
                interfaceStr = printerName;
            }

            const printer = new ThermalPrinter({
                type: PrinterTypes.EPSON, // Compatible con la mayoría de impresoras ESC/POS
                interface: interfaceStr,
                characterSet: CharacterSet.PC852_LATIN2,
                removeSpecialCharacters: false,
                lineCharacter: "-",
                breakLine: BreakLine.WORD,
                options: {
                    timeout: 15000, // Aumentar timeout para Windows e impresoras compartidas
                },
            });

            return printer;
        } catch (error) {
            const errorMsg = `Error creando impresora ${printerName}: ${error.message}`;
            console.error(errorMsg);
            this.emitLog(errorMsg, "error");
            throw new Error(
                `No se pudo inicializar la impresora "${printerName}": ${error.message}`
            );
        }
    }

    // Helper para verificar conexión con reintentos para impresoras compartidas
    async verifyPrinterConnection(printer, printerName) {
        try {
            await printer.isPrinterConnected();
            return true;
        } catch (connectionError) {
            const errorMsg = connectionError.message || String(connectionError);
            
            // Si es error de driver en Windows y no es impresora compartida, intentar formato alternativo
            if (process.platform === "win32" && 
                (errorMsg.includes("Driver no set") || errorMsg.includes("driver")) &&
                !printerName.includes("\\\\") && 
                !printerName.startsWith("\\\\")) {
                
                this.emitLog(`🔄 Intentando formato alternativo para impresora compartida...`, "info");
                try {
                    // Intentar con formato de red directo
                    const sharedPrinter = this.createPrinter(`\\\\${printerName}`);
                    await sharedPrinter.isPrinterConnected();
                    this.emitLog(`✅ Impresora compartida ${printerName} conectada con formato alternativo`, "success");
                    return { success: true, printer: sharedPrinter };
                } catch (altError) {
                    this.emitLog(`⚠️ Formato alternativo también falló: ${altError.message}`, "warning");
                }
            }
            
            // Si llegamos aquí, el error persiste
            throw connectionError;
        }
    }

    async openCashDrawer(printerName = "POS-80") {
        try {
            let printer = this.createPrinter(printerName);

            // Verificar conexión con mejor manejo de errores y reintentos
            try {
                const connectionResult = await this.verifyPrinterConnection(printer, printerName);
                
                // Si verifyPrinterConnection retornó un objeto con printer alternativo, usarlo
                if (connectionResult && connectionResult.printer) {
                    printer = connectionResult.printer;
                }
            } catch (connectionError) {
                const errorMsg =
                    connectionError.message || String(connectionError);
                if (
                    errorMsg.includes("Driver no set") ||
                    errorMsg.includes("driver")
                ) {
                    throw new Error(
                        `Impresora "${printerName}" no disponible. ` +
                        `Verifica que esté instalada, configurada y compartida correctamente en Windows. ` +
                        `Si es una impresora compartida, asegúrate de tener permisos de acceso.`
                    );
                }
                throw connectionError;
            }

            await printer.openCashDrawer();
            await printer.cut();
            return { success: true };
        } catch (error) {
            console.error("Error abriendo cajón:", error);
            throw error;
        }
    }

    async processSalePrint(data) {
        try {
            const printerName = data.printer || data.printerName || "POS-80";
            const openCash = data.open_cash || data.openCash || false;
            const useImage =
                data.print_settings?.use_image || data.useImage || false;
            const base64Image = data.image || data.base64Image;
            const logoBase64 = data.logo_base64 || data.logoBase64;
            const dataJson = data.data_json || data.dataJson || data;

            this.emitLog(`🖨️ Iniciando impresión de venta en impresora: ${printerName}`, "info");
            this.emitLog(`   - Abrir cajón: ${openCash}`, "debug");
            this.emitLog(`   - Usar imagen: ${useImage}`, "debug");
            this.emitLog(`   - Tiene imagen base64: ${!!base64Image}`, "debug");
            this.emitLog(`   - Tiene logo base64: ${!!logoBase64}`, "debug");
            this.emitLog(`   - Tiene datos JSON: ${!!dataJson}`, "debug");

            // Si tiene logoBase64 y no usa imagen, usar formato ESC/POS
            if (logoBase64 && !useImage && dataJson) {
                this.emitLog(`   → Usando método: ESC/POS con logo`, "info");
                await this.printSaleEscPos(
                    printerName,
                    dataJson,
                    openCash,
                    data.company,
                    logoBase64
                );
                this.emitLog(`✅ Impresión de venta completada exitosamente (ESC/POS con logo)`, "success");
            } else if (base64Image) {
                this.emitLog(`   → Usando método: Imagen base64`, "info");
                await this.printSaleImage(
                    printerName,
                    base64Image,
                    openCash,
                    logoBase64,
                    data.logo
                );
                this.emitLog(`✅ Impresión de venta completada exitosamente (Imagen)`, "success");
            } else if (dataJson && !useImage) {
                this.emitLog(`   → Usando método: ESC/POS sin logo`, "info");
                await this.printSaleEscPos(
                    printerName,
                    dataJson,
                    openCash,
                    data.company,
                    logoBase64
                );
                this.emitLog(`✅ Impresión de venta completada exitosamente (ESC/POS)`, "success");
            } else {
                const warnMsg = "⚠️ No se pudo determinar el método de impresión - datos insuficientes";
                this.emitLog(warnMsg, "warning");
                throw new Error(warnMsg);
            }
        } catch (error) {
            const errorMsg = `❌ Error procesando impresión de venta: ${error.message}`;
            this.emitLog(errorMsg, "error");
            console.error("Detalles del error:", error);
            throw error;
        }
    }

    async processOrderPrint(data) {
        try {
            const printerName = data.printer || data.printerName || "POS-80";
            const orderData = data.data_json || data.orderData || data;
            const openCash = data.open_cash || data.openCash || false;

            this.emitLog(`🖨️ Iniciando impresión de orden en impresora: ${printerName}`, "info");
            this.emitLog(`   - Abrir cajón: ${openCash}`, "debug");
            this.emitLog(`   - Tiene datos de orden: ${!!orderData}`, "debug");

            await this.printOrder(printerName, orderData, openCash);
            
            this.emitLog(`✅ Impresión de orden completada exitosamente`, "success");
        } catch (error) {
            const errorMsg = `❌ Error procesando impresión de orden: ${error.message}`;
            this.emitLog(errorMsg, "error");
            console.error("Detalles del error:", error);
            throw error;
        }
    }

    async printSaleImage(
        printerName,
        base64Image,
        openCash = false,
        logoBase64 = null,
        logoUrl = null
    ) {
        if (!base64Image) {
            throw new Error("No se proporcionó imagen para imprimir");
        }

        try {
            // Limpiar base64
            const cleanBase64 = base64Image.replace(
                /^data:image\/(png|jpeg|jpg);base64,/,
                ""
            );
            const imageBuffer = Buffer.from(cleanBase64, "base64");

            // Guardar imagen temporal
            const tempImagePath = path.join(
                this.tempDir,
                `temp_image_${Date.now()}.png`
            );
            await fs.writeFile(tempImagePath, imageBuffer);

            const printer = this.createPrinter(printerName);

            // Verificar conexión con mejor manejo de errores
            try {
                await printer.isPrinterConnected();
            } catch (connectionError) {
                const errorMsg =
                    connectionError.message || String(connectionError);
                if (
                    errorMsg.includes("Driver no set") ||
                    errorMsg.includes("driver")
                ) {
                    throw new Error(
                        `Impresora "${printerName}" no disponible. Verifica que esté instalada y configurada en Windows.`
                    );
                }
                throw connectionError;
            }

            // Imprimir logo si existe
            if (logoBase64) {
                const cleanLogoBase64 = logoBase64.replace(
                    /^data:image\/(png|jpeg|jpg);base64,/,
                    ""
                );
                const logoBuffer = Buffer.from(cleanLogoBase64, "base64");
                const tempLogoPath = path.join(
                    this.tempDir,
                    `temp_logo_${Date.now()}.png`
                );
                await fs.writeFile(tempLogoPath, logoBuffer);

                printer.alignCenter();
                await printer.printImage(tempLogoPath);
                printer.newLine();

                // Limpiar logo temporal
                await fs.unlink(tempLogoPath).catch(() => {});
            } else if (logoUrl) {
                // Descargar logo desde URL
                const axios = require("axios");
                const logoResponse = await axios.get(logoUrl, {
                    responseType: "arraybuffer",
                });
                const tempLogoPath = path.join(
                    this.tempDir,
                    `temp_logo_${Date.now()}.png`
                );
                await fs.writeFile(
                    tempLogoPath,
                    Buffer.from(logoResponse.data)
                );

                printer.alignCenter();
                await printer.printImage(tempLogoPath);
                printer.newLine();

                await fs.unlink(tempLogoPath).catch(() => {});
            }

            // Imprimir imagen principal
            printer.alignCenter();
            await printer.printImage(tempImagePath);
            printer.newLine();
            printer.cut();

            if (openCash) {
                await printer.openCashDrawer();
            }

            await printer.execute();

            // Limpiar imagen temporal
            await fs.unlink(tempImagePath).catch(() => {});
        } catch (error) {
            console.error("Error imprimiendo imagen:", error);
            throw error;
        }
    }

    async printSaleEscPos(
        printerName,
        saleData,
        openCash = false,
        company = null,
        logoBase64 = null
    ) {
        try {
            this.emitLog(`🔧 Creando instancia de impresora: ${printerName}`, "info");
            let printer = this.createPrinter(printerName);

            // Verificar conexión con mejor manejo de errores y reintentos
            try {
                this.emitLog(`🔍 Verificando conexión con impresora: ${printerName}`, "info");
                const connectionResult = await this.verifyPrinterConnection(printer, printerName);
                
                // Si verifyPrinterConnection retornó un objeto con printer alternativo, usarlo
                if (connectionResult && connectionResult.printer) {
                    printer = connectionResult.printer;
                }
                
                this.emitLog(`✅ Impresora ${printerName} está conectada`, "success");
            } catch (connectionError) {
                const errorMsg =
                    connectionError.message || String(connectionError);
                this.emitLog(`❌ Error verificando conexión: ${errorMsg}`, "error");
                if (
                    errorMsg.includes("Driver no set") ||
                    errorMsg.includes("driver")
                ) {
                    throw new Error(
                        `Impresora "${printerName}" no disponible. ` +
                        `Verifica que esté instalada, configurada y compartida correctamente en Windows. ` +
                        `Si es una impresora compartida, asegúrate de tener permisos de acceso.`
                    );
                }
                throw connectionError;
            }

            // Logo de la empresa
            if (logoBase64) {
                const cleanLogoBase64 = logoBase64.replace(
                    /^data:image\/(png|jpeg|jpg);base64,/,
                    ""
                );
                const logoBuffer = Buffer.from(cleanLogoBase64, "base64");
                const tempLogoPath = path.join(
                    this.tempDir,
                    `temp_logo_${Date.now()}.png`
                );
                await fs.writeFile(tempLogoPath, logoBuffer);

                printer.alignCenter();
                await printer.printImage(tempLogoPath);
                printer.newLine();

                await fs.unlink(tempLogoPath).catch(() => {});
            }

            // Encabezado de empresa
            if (company) {
                printer.alignCenter();
                const companyName = company.name || company.business_name || "";
                if (companyName) {
                    printer.setTextDoubleHeight();
                    printer.setTextDoubleWidth();
                    printer.bold(true);
                    printer.println(
                        this.normalizeText(companyName).toUpperCase()
                    );
                    printer.bold(false);
                    printer.setTextNormal();
                }

                const address = company.address || "";
                if (address) {
                    printer.println(
                        `DIRECCION: ${this.normalizeText(
                            address
                        ).toUpperCase()}`
                    );
                }

                const phone = company.phone || "";
                if (phone) {
                    printer.println(`CELULAR: ${this.normalizeText(phone)}`);
                }

                const nit = company.nit || "";
                if (nit) {
                    printer.println(`NIT: ${this.normalizeText(nit)}`);
                }
            }

            // Información de venta
            printer.alignRight();
            const billing = saleData.billing || "";
            if (billing) {
                printer.bold(true);
                printer.println(`VENTA: ${this.normalizeText(billing)}`);
                printer.bold(false);
            }

            const client = saleData.client || {};
            if (client) {
                const firstName = client.first_name || "";
                const firstSurname = client.first_surname || "";
                const clientName = `${firstName} ${firstSurname}`.trim();
                if (clientName) {
                    printer.bold(true);
                    printer.println(
                        `CLIENTE: ${this.normalizeText(
                            clientName
                        ).toUpperCase()}`
                    );
                    printer.bold(false);
                }

                const document = client.document || "";
                if (document) {
                    printer.bold(true);
                    printer.println(
                        `DOCUMENTO: ${this.normalizeText(document)}`
                    );
                    printer.bold(false);
                }
            }

            printer.newLine();

            // Productos
            printer.alignLeft();
            const itemsDetail = saleData.items_detail || [];
            if (itemsDetail.length > 0) {
                printer.bold(true);
                printer.println("ITEM                        CANT      VALOR");
                printer.bold(false);
                printer.drawLine();

                itemsDetail.forEach((item) => {
                    const product = item.product || {};
                    const productName = product.name || "Producto";
                    const quantity = item.quantity || 1;
                    const totalValue = item.total_value || 0;
                    const notes = item.note || "";

                    const nameNormalized = this.normalizeText(productName);
                    const nameTruncated =
                        nameNormalized.length > 28
                            ? nameNormalized.substring(0, 28)
                            : nameNormalized;

                    printer.println(
                        `${nameTruncated.toUpperCase().padEnd(28)} ${quantity
                            .toString()
                            .padStart(4)} ${this.formatCurrency(
                            totalValue
                        ).padStart(12)}`
                    );

                    if (notes) {
                        printer.println(
                            ` * ${this.normalizeText(notes).toUpperCase()}`
                        );
                    }
                });

                printer.drawLine();
            }

            // Totales
            printer.alignLeft();
            const subTotal = saleData.sub_total || 0;
            const totalTaxValue = saleData.total_tax_value || 0;
            const totalValue = saleData.total_value || 0;
            const totalTip = saleData.total_tip || 0;
            const discount = saleData.discount || 0;

            if (subTotal - totalTaxValue !== totalValue || totalTip > 0) {
                if (subTotal > 0) {
                    printer.println(
                        `SUBTOTAL                     ${this.formatCurrency(
                            subTotal - totalTaxValue
                        )}`
                    );
                }
            }

            if (discount > 0) {
                printer.println(
                    `DESCUENTO                   -${this.formatCurrency(
                        discount
                    )}`
                );
            }

            if (totalTaxValue > 0) {
                printer.println(
                    `IMPUESTO                     ${this.formatCurrency(
                        totalTaxValue
                    )}`
                );
            }

            if (totalTip > 0) {
                printer.println(
                    `PROPINA                      ${this.formatCurrency(
                        totalTip
                    )}`
                );
            }

            const finalTotal = totalValue + totalTip;
            printer.alignCenter();
            printer.setTextDoubleHeight();
            printer.bold(true);
            printer.println(`TOTAL ${this.formatCurrency(finalTotal)}`);
            printer.bold(false);
            printer.setTextNormal();
            printer.newLine();

            // Información adicional
            printer.alignLeft();
            const observation = saleData.observation || "";
            if (observation) {
                printer.bold(true);
                printer.println(
                    `Nota: ${this.normalizeText(observation).toUpperCase()}`
                );
                printer.bold(false);
            }

            const deliveryOrder = saleData.delivery_order || null;
            if (deliveryOrder) {
                const shippingAddress = deliveryOrder.shipping_address || "";
                if (shippingAddress) {
                    printer.bold(true);
                    printer.println(
                        `Direccion: ${this.normalizeText(shippingAddress)}`
                    );
                    printer.bold(false);
                }

                const phone = deliveryOrder.phone || "";
                if (phone) {
                    printer.bold(true);
                    printer.println(`Celular: ${this.normalizeText(phone)}`);
                    printer.bold(false);
                }

                const clientName = deliveryOrder.client_name || "";
                if (clientName) {
                    printer.bold(true);
                    printer.println(
                        `Referencia: ${this.normalizeText(clientName)}`
                    );
                    printer.bold(false);
                }
            }

            const tableOrder = saleData.table_order || null;
            if (tableOrder && tableOrder.table) {
                const tableName = tableOrder.table.name || "";
                const tableNumber = tableOrder.table.table_number || "";
                if (tableName && tableNumber) {
                    printer.bold(true);
                    printer.println(
                        `${this.normalizeText(tableName)}: ${this.normalizeText(
                            tableNumber
                        )}`
                    );
                    printer.bold(false);
                }
            }

            // Métodos de pago
            const paymentMethods = saleData.payment_methods || [];
            if (paymentMethods.length > 0) {
                printer.alignRight();
                if (paymentMethods.length === 1) {
                    const method = paymentMethods[0];
                    const methodName = method.name || "";
                    if (methodName) {
                        printer.bold(true);
                        printer.println(
                            `Forma de pago: ${this.normalizeText(methodName)}`
                        );
                        printer.bold(false);
                    }
                } else {
                    printer.bold(true);
                    printer.println("Formas de pago:");
                    printer.bold(false);
                    paymentMethods.forEach((method) => {
                        const methodName = method.name || "";
                        const amount = method.pivot?.amount || 0;
                        if (methodName) {
                            printer.println(
                                `${this.normalizeText(
                                    methodName
                                )}: ${this.formatCurrency(amount)}`
                            );
                        }
                    });
                }
            }

            // Cuotas
            const quotas = saleData.quotas || [];
            if (quotas.length > 0) {
                printer.newLine();
                printer.alignCenter();
                printer.bold(true);
                printer.println("Cuotas:");
                printer.bold(false);

                printer.alignLeft();
                printer.bold(true);
                printer.println("NUMERO   FECHA        VALOR");
                printer.bold(false);
                printer.drawLine();

                quotas.forEach((quota) => {
                    const number = quota.number || "";
                    const date = quota.date || "";
                    const value = quota.value || 0;
                    printer.println(
                        `${(number || "").padEnd(8)} ${(date || "").padEnd(
                            12
                        )} ${this.formatCurrency(value).padStart(12)}`
                    );
                });

                printer.newLine();
                printer.println(
                    this.normalizeText(
                        "Esta factura constituye título valor según Ley 1231/2008 de Colombia."
                    )
                );
                printer.println(
                    this.normalizeText(
                        "El cliente se compromete a pagar según fechas acordadas."
                    )
                );
                printer.newLine();
                printer.println("Firma: _____________________________");
                printer.println("ID: ___________________");
                printer.newLine();
            }

            // Pie de página
            printer.alignRight();
            const user = saleData.user || {};
            const userName = user.nickname || user.name || "";
            if (userName) {
                printer.bold(true);
                printer.println(
                    `Atendido por: ${this.normalizeText(userName)}`
                );
                printer.bold(false);
            }

            const createdAt = saleData.created_at || "";
            if (createdAt) {
                const date = new Date(createdAt);
                date.setHours(date.getHours() - 5); // Ajuste de zona horaria
                const formattedDate = date.toLocaleString("es-CO", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: true,
                });
                printer.bold(true);
                printer.println(`Generacion: ${formattedDate}`);
                printer.bold(false);
            }

            printer.newLine();

            const configResolution = saleData.config_resolution || {};
            const note = configResolution.note || "";
            if (note && note !== "null") {
                printer.alignCenter();
                printer.println(this.normalizeText(note));
                printer.newLine();
            }

            // CUFE y QR
            let cufe = saleData.cufe || "";
            if (!cufe || cufe === "null") {
                const invoiceSents = saleData.invoice_sents || [];
                if (invoiceSents.length > 0) {
                    cufe = invoiceSents[0].cufe || "";
                }
            }

            if (cufe && cufe !== "null" && cufe.toLowerCase() !== "null") {
                const qrUrl = `https://catalogo-vpfe.dian.gov.co/User/SearchDocument?documentkey=${cufe}`;
                printer.alignCenter();
                printer.bold(true);
                printer.println("CUFE:");
                printer.bold(false);
                await printer.printQR(qrUrl, {
                    cellSize: 4,
                    correction: "L",
                });
                printer.println(cufe);
                printer.newLine();
            }

            printer.alignCenter();
            printer.println(this.normalizeText("¡Gracias por tu compra!"));
            printer.newLine();
            const currentYear = new Date().getFullYear();
            printer.println(`GridPOS ${currentYear}`);

            printer.newLine();
            printer.newLine();
            printer.cut();

            if (openCash) {
                await printer.openCashDrawer();
            }

            this.emitLog(`📄 Enviando trabajo de impresión a ${printerName}...`, "info");
            await printer.execute();
            this.emitLog(`✅ Trabajo de impresión enviado exitosamente a ${printerName}`, "success");
        } catch (error) {
            const errorMsg = `❌ Error imprimiendo venta ESC/POS: ${error.message}`;
            this.emitLog(errorMsg, "error");
            console.error("Detalles del error:", error);
            throw error;
        }
    }

    async printOrder(printerName, orderData, openCash = false) {
        try {
            this.emitLog(`🔧 Creando instancia de impresora: ${printerName}`, "info");
            let printer = this.createPrinter(printerName);

            // Verificar conexión con mejor manejo de errores y reintentos
            try {
                this.emitLog(`🔍 Verificando conexión con impresora: ${printerName}`, "info");
                const connectionResult = await this.verifyPrinterConnection(printer, printerName);
                
                // Si verifyPrinterConnection retornó un objeto con printer alternativo, usarlo
                if (connectionResult && connectionResult.printer) {
                    printer = connectionResult.printer;
                }
                
                this.emitLog(`✅ Impresora ${printerName} está conectada`, "success");
            } catch (connectionError) {
                const errorMsg =
                    connectionError.message || String(connectionError);
                this.emitLog(`❌ Error verificando conexión: ${errorMsg}`, "error");
                if (
                    errorMsg.includes("Driver no set") ||
                    errorMsg.includes("driver")
                ) {
                    throw new Error(
                        `Impresora "${printerName}" no disponible. ` +
                        `Verifica que esté instalada, configurada y compartida correctamente en Windows. ` +
                        `Si es una impresora compartida, asegúrate de tener permisos de acceso.`
                    );
                }
                throw connectionError;
            }

            const paperWidth = orderData.print_settings?.paper_width || 80;
            const isSmallPaper = paperWidth === 58;

            printer.alignCenter();

            const clientName =
                orderData.order_data?.client_name ||
                orderData.client_info?.name ||
                null;
            if (clientName) {
                if (isSmallPaper) {
                    printer.setTextDoubleWidth();
                    printer.bold(true);
                    const nameFormatted =
                        clientName.length > 32
                            ? clientName.substring(0, 32)
                            : clientName;
                    printer.println(nameFormatted);
                } else {
                    printer.setTextDoubleWidth();
                    printer.setTextDoubleHeight();
                    printer.bold(true);
                    printer.println(clientName);
                }
            }

            printer.setTextNormal();
            printer.bold(false);
            printer.println(orderData.order_data?.date || "");

            const phone = orderData.order_data?.phone || "";
            if (phone) {
                printer.println(`CEL: ${phone}`);
            }

            const shippingAddress =
                orderData.order_data?.shipping_address || "";
            if (shippingAddress) {
                printer.println(`DIRECCION: ${shippingAddress}`);
            }

            printer.alignLeft();
            const separator = isSmallPaper ? "-".repeat(32) : "-".repeat(48);
            printer.println(separator);

            printer.bold(true);
            if (isSmallPaper) {
                printer.println("CANT  ITEM");
            } else {
                printer.println("CANT     ITEM");
            }
            printer.bold(false);
            printer.println(separator);

            const products = orderData.products || [];
            products.forEach((product, index) => {
                const qty = product.quantity || 1;
                const name = product.name || "Producto";
                const notes = product.notes || "";

                if (isSmallPaper) {
                    const qtyPadded = qty.toString().padEnd(2, " ");
                    printer.bold(true);
                    const maxNameChars = 28;
                    const nameFormatted =
                        name.length > maxNameChars
                            ? name.substring(0, maxNameChars)
                            : name;
                    printer.println(
                        `${qtyPadded}  ${nameFormatted.toUpperCase()}`
                    );
                    printer.bold(false);

                    if (name.length > maxNameChars) {
                        const remainingName = name.substring(maxNameChars);
                        printer.bold(true);
                        printer.println(`    ${remainingName.toUpperCase()}`);
                        printer.bold(false);
                    }
                } else {
                    printer.setTextDoubleWidth();
                    printer.bold(true);
                    const qtyPadded = qty.toString().padEnd(2, " ");
                    printer.println(`${qtyPadded}  ${name.toUpperCase()}`);
                    printer.bold(false);
                    printer.setTextNormal();
                }

                if (notes) {
                    printer.bold(true);
                    if (isSmallPaper) {
                        const maxNoteChars = 28;
                        const noteLines = this.wordWrap(notes, maxNoteChars);
                        noteLines.forEach((line) => {
                            printer.println(`  * ${line.toUpperCase()}`);
                        });
                    } else {
                        printer.println(`    * ${notes.toUpperCase()}`);
                    }
                    printer.bold(false);
                }

                if (index < products.length - 1) {
                    printer.newLine();
                }
            });

            printer.println(separator);

            const generalNote =
                orderData.order_data?.note || orderData.general_note || null;
            if (generalNote) {
                printer.bold(true);
                printer.println(`NOTA: ${generalNote.toUpperCase()}`);
                printer.bold(false);
                printer.newLine();
            }

            const userName =
                orderData.user?.name || orderData.user?.nickname || "Sistema";
            printer.println(`Atendido por: ${userName}`);
            printer.println(
                `Impresión: ${orderData.order_data?.date_print || ""}`
            );

            const orderIdDisplay = shippingAddress
                ? orderData.order_data?.order_number
                : orderData.order_data?.id || "1";
            printer.bold(true);
            printer.println(`ORDEN: ${orderIdDisplay}`);
            printer.bold(false);

            printer.newLine();
            printer.newLine();
            printer.cut();

            if (openCash) {
                await printer.openCashDrawer();
            }

            this.emitLog(`📄 Enviando trabajo de impresión a ${printerName}...`, "info");
            await printer.execute();
            this.emitLog(`✅ Trabajo de impresión enviado exitosamente a ${printerName}`, "success");
        } catch (error) {
            const errorMsg = `❌ Error imprimiendo orden: ${error.message}`;
            this.emitLog(errorMsg, "error");
            console.error("Detalles del error:", error);
            throw error;
        }
    }

    async testPrint(printerName) {
        try {
            const printer = this.createPrinter(printerName);

            // Verificar conexión con mejor manejo de errores
            try {
                await printer.isPrinterConnected();
            } catch (connectionError) {
                const errorMsg =
                    connectionError.message || String(connectionError);

                // Detectar error específico de driver
                if (
                    errorMsg.includes("Driver no set") ||
                    errorMsg.includes("driver") ||
                    errorMsg.includes("not found") ||
                    errorMsg.includes("No se puede encontrar")
                ) {
                    throw new Error(
                        `Impresora "${printerName}" no encontrada o driver no configurado. ` +
                            `Por favor verifica que la impresora esté instalada y configurada en Windows. ` +
                            `Error: ${errorMsg}`
                    );
                }

                // Re-lanzar otros errores
                throw connectionError;
            }

            printer.alignCenter();
            printer.setTextDoubleHeight();
            printer.setTextDoubleWidth();
            printer.bold(true);
            printer.println("PRUEBA DE IMPRESION");
            printer.setTextNormal();
            printer.bold(false);
            printer.newLine();
            printer.println("GridPOS Printer");
            printer.println(new Date().toLocaleString());
            printer.newLine();
            printer.cut();

            await printer.execute();
            return { success: true, message: "Impresión de prueba exitosa" };
        } catch (error) {
            const errorMsg = error.message || String(error);
            console.error("Error en prueba de impresión:", errorMsg);

            // Proporcionar mensaje más descriptivo
            if (
                errorMsg.includes("Driver no set") ||
                errorMsg.includes("driver")
            ) {
                throw new Error(
                    `Error de driver de impresora: La impresora "${printerName}" no está disponible o su driver no está configurado correctamente. ` +
                        `Por favor verifica en Configuración > Impresoras y escáneres que la impresora esté instalada y lista.`
                );
            }

            throw error;
        }
    }

    formatCurrency(amount) {
        try {
            if (amount === Math.floor(amount)) {
                return `$ ${amount.toLocaleString("es-CO")}`;
            }
            return `$ ${amount.toLocaleString("es-CO", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        } catch (error) {
            return `$ ${amount}`;
        }
    }

    normalizeText(text) {
        if (!text) return "";

        return String(text)
            .replace(/ñ/g, "n")
            .replace(/Ñ/g, "N")
            .replace(/á/g, "a")
            .replace(/é/g, "e")
            .replace(/í/g, "i")
            .replace(/ó/g, "o")
            .replace(/ú/g, "u")
            .replace(/ü/g, "u")
            .replace(/Á/g, "A")
            .replace(/É/g, "E")
            .replace(/Í/g, "I")
            .replace(/Ó/g, "O")
            .replace(/Ú/g, "U")
            .replace(/Ü/g, "U")
            .replace(/¿/g, "?")
            .replace(/¡/g, "!")
            .replace(/°/g, "o")
            .trim();
    }

    wordWrap(text, maxChars) {
        if (text.length <= maxChars) {
            return [text];
        }

        const words = text.split(" ");
        const lines = [];
        let currentLine = "";

        words.forEach((word) => {
            if (word.length > maxChars) {
                if (currentLine) {
                    lines.push(currentLine.trim());
                    currentLine = "";
                }
                const chunks = [];
                for (let i = 0; i < word.length; i += maxChars) {
                    chunks.push(word.substring(i, i + maxChars));
                }
                lines.push(...chunks);
                return;
            }

            const testLine = currentLine ? `${currentLine} ${word}` : word;
            if (testLine.length <= maxChars) {
                currentLine = testLine;
            } else {
                if (currentLine) {
                    lines.push(currentLine.trim());
                }
                currentLine = word;
            }
        });

        if (currentLine) {
            lines.push(currentLine.trim());
        }

        return lines;
    }
}

module.exports = PrinterService;
