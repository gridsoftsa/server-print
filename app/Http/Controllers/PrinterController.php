<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;

class PrinterController extends Controller
{
    public function openCash($name = 'POS-80')
    {
        $connector = new WindowsPrintConnector($name);
        $printer = new Printer($connector);

        try {
            $printer->pulse();
            $printer->close();
            return response()->json(['message' => 'Caja abierta'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al abrir la caja', 'error' => $e->getMessage()], 500);
        }
    }

    public function printOrder(Request $request)
    {
        ini_set('memory_limit', '1024M');
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $orderData = $request->orderData;

        try {
            $startTime = microtime(true);

            // Crear conexión directa con la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Configurar papel según ancho
            $paperWidth = isset($orderData['print_settings']['paper_width']) ?
                (int)$orderData['print_settings']['paper_width'] : 80; // 🚀 FIX: Proper paper_width handling
            $isSmallPaper = $paperWidth == 58;

            // === ENCABEZADO ===
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // Cliente si existe - Ajustado por tamaño de papel
            $clientName = $orderData['order_data']['client_name'] ?? $orderData['client_info']['name'] ?? null;

            if ($isSmallPaper) {
                // 📱 Para papel 58mm: usar solo EMPHASIZED (texto moderado)
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);

                // Limitar nombre del cliente a 32 caracteres para 58mm
                if ($clientName && strlen($clientName) > 32) {
                    $clientNameFormatted = substr($clientName, 0, 32);
                } else {
                    $clientNameFormatted = $clientName;
                }

                $printer->text($clientNameFormatted . "\n");
            } else {
                // 🖨️ Para papel 80mm: texto grande normal
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                $printer->text($clientName . "\n");
            }

            $printer->selectPrintMode(); // Reset
            //$printer->feed(1);

            // Fecha de la orden
            $printer->text($orderData['order_data']['date'] . "\n");

            //Si existe el phone de la empresa, imprimirlo
            if (!empty($orderData['order_data']['phone'])) {
                $printer->text("CEL: " . $orderData['order_data']['phone'] . "\n");
            }

            //Agregar la direccion de shipping_address si existe
            if (!empty($orderData['order_data']['shipping_address'])) {
                $printer->text("DIRECCION: " . $orderData['order_data']['shipping_address'] . "\n");
            }

            // === SEPARADOR GRUESO ===
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $separator = $isSmallPaper ? str_repeat('-', 32) : str_repeat('-', 48);
            $printer->text($separator . "\n");

            // ENCABEZADOS DE COLUMNAS - Ajustado para tamaño de papel
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            if ($isSmallPaper) {
                $printer->text("CANT  ITEM\n"); // Más compacto para 58mm
            } else {
                $printer->text("CANT     ITEM\n"); // Formato normal para 80mm
            }
            $printer->selectPrintMode(); // Reset
            $printer->text($separator . "\n");

            // === PRODUCTOS - FORMATO OPTIMIZADO PARA TAMAÑO DE PAPEL ===
            $products = $orderData['products'] ?? [];
            $productCount = count($products);
            $currentIndex = 0;

            foreach ($products as $product) {
                $currentIndex++;
                $qty = $product['quantity'] ?? 1;
                $name = $product['name'] ?? 'Producto';
                $notes = $product['notes'] ?? '';

                if ($isSmallPaper) {
                    // 📱 FORMATO PARA PAPEL 58MM - Texto moderado sin cortes
                    $qtyPadded = str_pad($qty, 2, ' ', STR_PAD_RIGHT);

                    // Usar solo EMPHASIZED para 58mm (sin DOUBLE_WIDTH que corta el texto)
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);

                    // Calcular espacio disponible: 32 chars - 2 qty - 2 espacios = 28 chars para nombre
                    $maxNameChars = 28;
                    $nameFormatted = strlen($name) > $maxNameChars ? substr($name, 0, $maxNameChars) : $name;

                    $printer->text($qtyPadded . "  " . strtoupper($nameFormatted) . "\n");
                    $printer->selectPrintMode(); // Reset

                    // Si el nombre fue cortado, imprimir el resto en la siguiente línea
                    if (strlen($name) > $maxNameChars) {
                        $remainingName = substr($name, $maxNameChars);
                        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                        $printer->text("    " . strtoupper($remainingName) . "\n");
                        $printer->selectPrintMode(); // Reset
                    }
                } else {
                    // 🖨️ FORMATO PARA PAPEL 80MM - Texto grande normal
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                    $qtyPadded = str_pad($qty, 2, ' ', STR_PAD_RIGHT);
                    $printer->text($qtyPadded . "  " . strtoupper($name) . "\n");
                    $printer->selectPrintMode(); // Reset
                }

                // Notas del producto si existen (ajustadas por tamaño de papel)
                if (!empty($notes) && $notes !== null) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);

                    if ($isSmallPaper) {
                        // Para 58mm: limitar notas a 28 caracteres por línea
                        $maxNoteChars = 28;
                        $noteLines = $this->wordWrapEscPos($notes, $maxNoteChars);
                        foreach ($noteLines as $noteLine) {
                            $printer->text("  * " . strtoupper($noteLine) . "\n");
                        }
                    } else {
                        // Para 80mm: formato normal
                        $printer->text("    * " . strtoupper($notes) . "\n");
                    }

                    $printer->selectPrintMode(); // Reset
                }

                // Agregar espacio solo si no es el último producto
                if ($currentIndex < $productCount) {
                    $printer->text("\n"); // Pequeño espacio entre productos
                }
            }

            // === SEPARADOR FINAL ===
            $printer->text($separator . "\n");
            //$printer->feed(1);

            // NOTA GENERAL si existe
            $generalNote = $orderData['order_data']['note'] ?? $orderData['general_note'] ?? null;
            if (!empty($generalNote) && $generalNote !== null) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("NOTA: " . strtoupper($generalNote) . "\n");
                $printer->selectPrintMode(); // Reset
                $printer->feed(1);
            }

            // === PIE DE PÁGINA ===
            // Usuario que atiende
            $userName = $orderData['user']['name'] ?? $orderData['user']['nickname'] ?? 'Sistema';
            $printer->text("Atendido por: " . $userName . "\n");

            // Timestamp de impresión
            $printer->text("Impresión: " . $orderData['order_data']['date_print'] . "\n");

            // ID de orden más visible
            $orderIdDisplay = !empty($orderData['order_data']['shipping_address']) ?
                $orderData['order_data']['order_number'] : ($orderData['order_data']['id'] ?? '1');
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("ORDEN: " . $orderIdDisplay . "\n");
            $printer->selectPrintMode(); // Reset

            $printer->feed(1);
            $printer->cut();

            // Abrir caja si se requiere
            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'message' => 'Orden impresa correctamente con ESC/POS',
                'execution_time_ms' => $executionTime,
                'mode' => 'escpos_optimized'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al imprimir la orden con ESC/POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🚀 MÉTODO ULTRA RÁPIDO: Imprimir venta con imagen - OPTIMIZACIÓN MÁXIMA
     */
    public function printSale(Request $request)
    {
        try {
            // 🚀 OPTIMIZACIÓN 1: Configurar memoria y timeouts para máxima velocidad
            ini_set('memory_limit', '1024M');
            $printerName = $request->printerName;
            $base64Image = $request->base64Image;
            $openCash = $request->openCash;
            $logoBase64 = $request->logoBase64;
            $logo = $request->logo;

            // 🚀 OPTIMIZACIÓN 2: Validación ultra rápida
            if (empty($base64Image)) {
                return;
            }

            // 🚀 OPTIMIZACIÓN 3: Decodificar base64 directamente sin regex lento
            $imageData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $base64Image));

            // 🚀 OPTIMIZACIÓN 4: Usar directorio temporal del sistema (más rápido)
            $tempPath = storage_path('app/public/temp_image.png');
            file_put_contents($tempPath, $imageData);

            // 🚀 OPTIMIZACIÓN 5: PRIORIZAR logo_base64 SOBRE logo URL
            $tempPathLogo = null;
            if ($logoBase64 && !empty($logoBase64)) {
                // 🚀 PRIORIDAD ALTA: Usar logo_base64 directamente
                $logoData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $logoBase64));
                $tempPathLogo = storage_path('app/public/temp_logo.png');
                file_put_contents($tempPathLogo, $logoData);
            } elseif ($logo && !empty($logo)) {
                // 🚀 FALLBACK: Usar logo URL si no hay logo_base64
                $logoHash = md5($logo);
                $cacheDir = storage_path('app/public/logo_cache');
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                $tempPathLogo = $cacheDir . '/company_logo_' . $logoHash . '.png';

                if (!file_exists($tempPathLogo)) {
                    $logoData = file_get_contents($logo);
                    if ($logoData !== false) {
                        file_put_contents($tempPathLogo, $logoData);
                    } else {
                        $tempPathLogo = null;
                    }
                }
            }

            // 🚀 OPTIMIZACIÓN 6: Conexión directa a impresora sin validaciones extra
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // 🚀 OPTIMIZACIÓN 7: Imprimir logo si existe
            if ($tempPathLogo && file_exists($tempPathLogo)) {
                $imgLogo = EscposImage::load($tempPathLogo);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);
            }

            // 🚀 OPTIMIZACIÓN 8: Imprimir imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(1);
            $printer->cut();

            // 🚀 OPTIMIZACIÓN 9: Abrir caja si es necesario
            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            // 🚀 OPTIMIZACIÓN 10: Limpieza ultra rápida
            @unlink($tempPath); // Eliminar imagen de factura temporal

            // Limpiar logo temporal si es base64 (no caché)
            if ($tempPathLogo && strpos($tempPathLogo, 'logo_') !== false) {
                @unlink($tempPathLogo); // Solo archivos temporales base64
            }
            // Los archivos de caché (logo_cache/) se mantienen para reutilización
        } catch (\Exception $e) {
            // 🚀 OPTIMIZACIÓN: Limpieza en caso de error
            @unlink($tempPath ?? '');

            // Limpiar logo temporal si es base64 (no caché)
            if (isset($tempPathLogo) && $tempPathLogo && strpos($tempPathLogo, 'logo_') !== false) {
                @unlink($tempPathLogo); // Solo archivos temporales base64
            }
        }
    }

    /**
     * 🧾 Imprimir venta completa con ESC/POS (basado en SaleFormatter.kt)
     * Maneja el formato completo de factura como TicketPrint.vue
     */
    public function printSaleEscPos(Request $request)
    {
        try {
            // 🚀 Configuración de memoria y datos
            ini_set('memory_limit', '1024M');
            $printerName = $request->input('printerName', 'POS-80');
            $openCash = $request->input('openCash', false);
            $saleData = $request->input('dataJson', $request->all());
            $company = $request->input('company', null);
            $logoBase64 = $request->input('logoBase64', null);

            // 🖨️ Crear conexión con impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // 🔧 Inicializar impresora
            $printer->initialize();

            // === ENCABEZADO DE EMPRESA ===
            $this->printCompanyHeader($printer, $company, $logoBase64);

            // === INFORMACIÓN DE VENTA Y CLIENTE ===
            $this->printSaleInfo($printer, $saleData);

            // === PRODUCTOS ===
            $this->printProducts($printer, $saleData);

            // === TOTALES ===
            $this->printTotals($printer, $saleData);

            // === INFORMACIÓN ADICIONAL ===
            $this->printAdditionalInfo($printer, $saleData);

            // === PIE DE PÁGINA ===
            $this->printFooter($printer, $saleData);

            // === FINALIZACIÓN ===
            $printer->feed(2);
            $printer->cut();

            // === ABRIR CAJA SI ES NECESARIO ===
            if ($openCash) {
                Log::info('💰 Abriendo caja registradora...');
                $printer->pulse();
            }

            $printer->close();
            return response()->json([
                'message' => 'Venta impresa correctamente con ESC/POS',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            Log::error('❌ Error imprimiendo venta ESC/POS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al imprimir venta con ESC/POS',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * 🏢 Imprimir encabezado de empresa (centrado como TicketPrint.vue)
     */
    private function printCompanyHeader($printer, $company, $logoBase64)
    {
        try {
            if ($company) {
                // ✅ USAR DIRECTAMENTE COMPANY - NO FALLBACK A SUBSIDIARY
                $companyName = $company['name'] ?? $company['business_name'] ?? '';
                $companyAddress = $company['address'] ?? '';
                $companyPhone = $company['phone'] ?? '';
                $companyNit = $company['nit'] ?? '';
            }

            // ✅ Logo Base64
            $logoBase64 = $logoBase64 ?? null;

            if (!empty($logoBase64) && $logoBase64 !== 'null' && trim($logoBase64) !== '') {
                Log::info('🖼️ Logo Base64 detectado, imprimiendo...');
                $this->printCompanyLogo($printer, $logoBase64);
            } else {
                Log::info('⚠️ No se encontró logo válido (ni Base64 ni URL)');
            }

            // === INFORMACIÓN DE EMPRESA CENTRADA ===
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // ✅ Nombre de la empresa (centrado, negrita, doble altura)
            if (!empty($companyName)) {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                $printer->text(strtoupper($this->normalizeText($companyName)) . "\n");
                $printer->selectPrintMode(); // Reset
                Log::info('🏢 Nombre empresa impreso: ' . $companyName);
            }

            // ✅ Dirección (centrada)
            if (!empty($companyAddress)) {
                $printer->text("DIRECCION: " . strtoupper($this->normalizeText($companyAddress)) . "\n");
                Log::info('🏢 Dirección impresa: ' . $companyAddress);
            }

            // ✅ Teléfono/Celular (centrado)
            if (!empty($companyPhone)) {
                $printer->text("CELULAR: " . $this->normalizeText($companyPhone) . "\n");
                Log::info('🏢 Teléfono impreso: ' . $companyPhone);
            }

            // ✅ NIT (centrado) - Prioridad: Company.nit > company_id
            if (!empty($companyNit)) {
                $printer->text("NIT: " . $this->normalizeText($companyNit) . "\n");
                Log::info('🏢 NIT impreso: ' . $companyNit);
            }

            $printer->feed(1);
        } catch (\Exception $e) {
            Log::error('❌ Error en encabezado de empresa', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 📋 Imprimir información de venta y cliente (alineada derecha como TicketPrint.vue)
     */
    private function printSaleInfo($printer, $saleData)
    {
        try {
            Log::info('📋 Imprimiendo información de venta...');

            $printer->setJustification(Printer::JUSTIFY_RIGHT);

            // Número de venta/factura
            $billing = $saleData['billing'] ?? '';
            if (!empty($billing)) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("VENTA: " . $this->normalizeText($billing) . "\n");
                $printer->selectPrintMode(); // Reset
                Log::info('📋 Número de venta impreso: ' . $billing);
            }

            // Cliente
            $client = $saleData['client'] ?? [];
            if (!empty($client)) {
                $firstName = $client['first_name'] ?? '';
                $firstSurname = $client['first_surname'] ?? '';
                $clientName = trim($firstName . ' ' . $firstSurname);

                Log::info('📋 Datos de cliente obtenidos', [
                    'first_name' => $firstName,
                    'first_surname' => $firstSurname,
                    'full_name' => $clientName
                ]);

                if (!empty($clientName)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("CLIENTE: " . strtoupper($this->normalizeText($clientName)) . "\n");
                    $printer->selectPrintMode(); // Reset
                    Log::info('📋 Cliente impreso: ' . $clientName);
                }

                // Documento
                $document = $client['document'] ?? '';
                if (!empty($document)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("DOCUMENTO: " . $this->normalizeText($document) . "\n");
                    $printer->selectPrintMode(); // Reset
                    Log::info('📋 Documento impreso: ' . $document);
                }
            }

            $printer->feed(1);
        } catch (\Exception $e) {
            Log::error('❌ Error en información de venta', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 🛒 Imprimir productos/items (optimizado para 80mm)
     */
    private function printProducts($printer, $saleData)
    {
        try {
            Log::info('🛒 Imprimiendo productos...');

            $itemsDetail = $saleData['items_detail'] ?? [];
            if (empty($itemsDetail)) {
                Log::warning('⚠️ No se encontraron productos en items_detail');
                return;
            }

            Log::info('🛒 Encontrados ' . count($itemsDetail) . ' productos');

            // Encabezado de productos
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("ITEM                        CANT      VALOR\n");
            $printer->selectPrintMode(); // Reset

            // Línea separadora para papel 80mm (48 caracteres)
            $printer->text(str_repeat('-', 48) . "\n");

            // Imprimir cada producto
            foreach ($itemsDetail as $item) {
                $product = $item['product'] ?? [];
                $productName = $product['name'] ?? 'Producto';
                $quantity = $item['quantity'] ?? 1;
                $totalValue = $item['total_value'] ?? 0;
                $notes = $item['note'] ?? '';

                // Normalizar y truncar nombre del producto
                $nameNormalized = $this->normalizeText($productName);
                $nameTruncated = strlen($nameNormalized) > 28 ? substr($nameNormalized, 0, 28) : $nameNormalized;

                // Formatear línea del producto
                $line = sprintf(
                    "%-28s %4d %12s",
                    strtoupper($nameTruncated),
                    $quantity,
                    $this->formatCurrency($totalValue)
                );

                $printer->text($line . "\n");

                // Imprimir notas si existen
                if (!empty($notes) && $notes !== null) {
                    $printer->text(" * " . strtoupper($this->normalizeText($notes)) . "\n");
                }
            }

            // Línea separadora final
            $printer->text(str_repeat('-', 48) . "\n");
        } catch (\Exception $e) {
            Log::error('❌ Error en productos', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 💰 Imprimir totales completos (como TicketPrint.vue)
     */
    private function printTotals($printer, $saleData)
    {
        try {
            Log::info('💰 Imprimiendo totales...');

            $printer->setJustification(Printer::JUSTIFY_LEFT);

            // Obtener valores de totales
            $subTotal = $saleData['sub_total'] ?? 0;
            $totalTaxValue = $saleData['total_tax_value'] ?? 0;
            $totalValue = $saleData['total_value'] ?? 0;
            $totalTip = $saleData['total_tip'] ?? 0;
            $discount = $saleData['discount'] ?? 0;

            Log::info('💰 Valores obtenidos', [
                'sub_total' => $subTotal,
                'total_tax_value' => $totalTaxValue,
                'total_value' => $totalValue,
                'total_tip' => $totalTip,
                'discount' => $discount
            ]);

            // SUBTOTAL (si es diferente del total o hay propina)
            if (($subTotal - $totalTaxValue != $totalValue) || $totalTip > 0) {
                if ($subTotal > 0) {
                    $printer->text(sprintf(
                        "SUBTOTAL                     %s\n",
                        $this->formatCurrency($subTotal - $totalTaxValue)
                    ));
                }
            }

            // DESCUENTO (si existe)
            if ($discount > 0) {
                $printer->text(sprintf(
                    "DESCUENTO                   -%s\n",
                    $this->formatCurrency($discount)
                ));
            }

            // IMPUESTO (si existe)
            if ($totalTaxValue > 0) {
                $printer->text(sprintf(
                    "IMPUESTO                     %s\n",
                    $this->formatCurrency($totalTaxValue)
                ));
            }

            // PROPINA (si existe)
            if ($totalTip > 0) {
                $printer->text(sprintf(
                    "PROPINA                      %s\n",
                    $this->formatCurrency($totalTip)
                ));
            }

            // TOTAL FINAL (grande y centrado)
            $finalTotal = $totalValue + $totalTip;
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->text("TOTAL " . $this->formatCurrency($finalTotal) . "\n");
            $printer->selectPrintMode(); // Reset

            $printer->feed(1);
        } catch (\Exception $e) {
            Log::error('❌ Error en totales', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ℹ️ Imprimir información adicional completa (como TicketPrint.vue)
     */
    private function printAdditionalInfo($printer, $saleData)
    {
        try {
            Log::info('ℹ️ Imprimiendo información adicional...');

            $printer->setJustification(Printer::JUSTIFY_LEFT);

            // OBSERVACIONES DE LA VENTA
            $observation = $saleData['observation'] ?? '';
            if (!empty($observation) && $observation !== null) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Nota: " . strtoupper($this->normalizeText($observation)) . "\n");
                $printer->selectPrintMode(); // Reset
            }

            // INFORMACIÓN DE DELIVERY
            $deliveryOrder = $saleData['delivery_order'] ?? null;
            if (!empty($deliveryOrder)) {
                $shippingAddress = $deliveryOrder['shipping_address'] ?? '';
                if (!empty($shippingAddress)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Direccion: " . $this->normalizeText($shippingAddress) . "\n");
                    $printer->selectPrintMode(); // Reset
                }

                $phone = $deliveryOrder['phone'] ?? '';
                if (!empty($phone)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Celular: " . $this->normalizeText($phone) . "\n");
                    $printer->selectPrintMode(); // Reset
                }

                $clientName = $deliveryOrder['client_name'] ?? '';
                if (!empty($clientName)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Referencia: " . $this->normalizeText($clientName) . "\n");
                    $printer->selectPrintMode(); // Reset
                }
            }

            // INFORMACIÓN DE MESA (si existe)
            $tableOrder = $saleData['table_order'] ?? null;
            if (!empty($tableOrder)) {
                $tableName = $tableOrder['table']['name'] ?? '';
                $tableNumber = $tableOrder['table']['table_number'] ?? '';

                if (!empty($tableName) && !empty($tableNumber)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text($this->normalizeText($tableName) . ": " . $this->normalizeText($tableNumber) . "\n");
                    $printer->selectPrintMode(); // Reset
                }
            }

            // FORMAS DE PAGO (mejorado como SaleFormatter.kt)
            $paymentMethods = $saleData['payment_methods'] ?? [];
            if (!empty($paymentMethods)) {
                Log::info('💳 Procesando métodos de pago', ['count' => count($paymentMethods)]);

                if (count($paymentMethods) == 1) {
                    // Una sola forma de pago
                    $method = $paymentMethods[0];
                    $methodName = $method['name'] ?? '';
                    if (!empty($methodName)) {
                        $printer->setJustification(Printer::JUSTIFY_RIGHT);
                        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                        $printer->text("Forma de pago: " . $this->normalizeText($methodName) . "\n");
                        $printer->selectPrintMode(); // Reset
                        Log::info('💳 Método único impreso: ' . $methodName);
                    }
                } else {
                    // Múltiples formas de pago
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Formas de pago:\n");
                    $printer->selectPrintMode(); // Reset

                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    foreach ($paymentMethods as $method) {
                        $methodName = $method['name'] ?? '';
                        $amount = $method['pivot']['amount'] ?? 0;

                        if (!empty($methodName)) {
                            $printer->text($this->normalizeText($methodName) . ": " . $this->formatCurrency($amount) . "\n");
                            Log::info('💳 Método múltiple impreso: ' . $methodName . ' - ' . $amount);
                        }
                    }
                }
            }

            // ✅ CUOTAS (si existen) - como SaleFormatter.kt
            $quotas = $saleData['quotas'] ?? [];
            Log::info('💰 Verificando cuotas', ['count' => count($quotas)]);
            if (!empty($quotas)) {
                $printer->feed(1);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Cuotas:\n");
                $printer->selectPrintMode(); // Reset

                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("NUMERO   FECHA        VALOR\n");
                $printer->selectPrintMode(); // Reset

                // Línea separadora
                $printer->text(str_repeat('-', 48) . "\n");

                foreach ($quotas as $quota) {
                    $number = $quota['number'] ?? '';
                    $date = $quota['date'] ?? '';
                    $value = $quota['value'] ?? 0;

                    $quotaLine = sprintf(
                        "%-8s %-12s %12s",
                        $number,
                        $date,
                        $this->formatCurrency($value)
                    );
                    $printer->text($quotaLine . "\n");
                    Log::info('💰 Cuota impresa: ' . $quotaLine);
                }

                $printer->feed(1);
                // Textos legales para cuotas
                $printer->text($this->normalizeText("Esta factura constituye título valor según Ley 1231/2008 de Colombia.\n"));
                $printer->text($this->normalizeText("El cliente se compromete a pagar según fechas acordadas.\n"));
                $printer->feed(1);
                $printer->text("Firma: _____________________________\n");
                $printer->text("ID: ___________________\n");
                $printer->feed(1);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error en información adicional', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 📄 Imprimir pie de página completo (como TicketPrint.vue)
     */
    private function printFooter($printer, $saleData)
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_RIGHT);

            // ATENDIDO POR
            $user = $saleData['user'] ?? [];
            $userName = $user['nickname'] ?? $user['name'] ?? '';
            if (!empty($userName)) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Atendido por: " . $this->normalizeText($userName) . "\n");
                $printer->selectPrintMode(); // Reset
            }

            // FECHA DE CREACIÓN
            $createdAt = $saleData['created_at'] ?? '';
            if (!empty($createdAt)) {
                // Formatear fecha
                $date = date('d/m/Y h:i:s A', strtotime($createdAt) - 18000); // UTC-5
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Generacion: " . $date . "\n");
                $printer->selectPrintMode(); // Reset
            }

            $printer->feed(1);

            // RESOLUCIÓN DIAN (como SaleFormatter.kt)
            $configResolution = $saleData['config_resolution'] ?? [];
            $note = $configResolution['note'] ?? '';
            if (!empty($note) && $note !== 'null') {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($this->normalizeText($note) . "\n");
                $printer->feed(1);
                Log::info('📄 Resolución DIAN impresa: ' . $note);
            }

            // ✅ QR CODE CON CUFE (implementado como SaleFormatter.kt)
            $cufe = $saleData['cufe'] ?? '';
            // También buscar en invoice_sents como fallback
            if (empty($cufe) || $cufe === 'null') {
                $invoiceSents = $saleData['invoice_sents'] ?? [];
                if (!empty($invoiceSents)) {
                    $cufe = $invoiceSents[0]['cufe'] ?? '';
                }
            }

            if (!empty($cufe) && $cufe !== 'null' && strtolower($cufe) !== 'null') {
                // URL exacta como SaleFormatter.kt y TicketPrint.vue
                $qrUrl = "https://catalogo-vpfe.dian.gov.co/User/SearchDocument?documentkey=" . $cufe;

                // Imprimir etiqueta CUFE centrada
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("CUFE:\n");
                $printer->selectPrintMode(); // Reset

                // ✅ Generar e imprimir QR Code
                $this->printQRCode($printer, $qrUrl);

                // CUFE como texto (simulando que está al lado como SaleFormatter.kt)
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($cufe . "\n");
                $printer->feed(1);
                Log::info('✅ QR y CUFE texto impresos');
            } else {
                Log::info('⚠️ CUFE no válido para QR: ' . $cufe);
            }

            // MENSAJE DE AGRADECIMIENTO (normalizado)
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($this->normalizeText("¡Gracias por tu compra!\n"));
            $printer->feed(1);
            // FOOTER GRIDPOS
            $currentYear = date('Y');
            $printer->text("GridPOS $currentYear\n");
        } catch (\Exception $e) {
            Log::error('❌ Error en pie de página', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 💲 Formatear moneda - Compatible con caracteres térmicos
     */
    private function formatCurrency($amount)
    {
        try {
            // Formato simple sin símbolo especial para impresoras térmicas
            if ($amount == (int)$amount) {
                // Sin decimales si es número entero
                return '$ ' . number_format($amount, 0, ',', ',');
            } else {
                // Con decimales si es necesario
                return '$ ' . number_format($amount, 2, ',', ',');
            }
        } catch (\Exception $e) {
            return '$ ' . number_format($amount, 0);
        }
    }

    /**
     * 🌍 Normalizar texto para impresoras térmicas (eliminar caracteres especiales)
     */
    private function normalizeText($text)
    {
        if (empty($text)) return '';

        return str_replace([
            'ñ',
            'Ñ',
            'á',
            'é',
            'í',
            'ó',
            'ú',
            'ü',
            'Á',
            'É',
            'Í',
            'Ó',
            'Ú',
            'Ü',
            '¿',
            '¡',
            '°'
        ], [
            'n',
            'N',
            'a',
            'e',
            'i',
            'o',
            'u',
            'u',
            'A',
            'E',
            'I',
            'O',
            'U',
            'U',
            '?',
            '!',
            'o'
        ], trim($text));
    }

    /**
     * 🖼️ Imprimir logo de la empresa desde Base64
     */
    private function printCompanyLogo($printer, $logoBase64)
    {
        try {
            Log::info('🖼️ Procesando logo Base64...', [
                'original_length' => strlen($logoBase64),
                'starts_with_data' => strpos($logoBase64, 'data:image') === 0
            ]);

            // ✅ Limpiar el prefijo data:image si existe
            $cleanBase64 = $logoBase64;
            if (strpos($logoBase64, 'data:image') === 0) {
                $commaPos = strpos($logoBase64, ',');
                if ($commaPos !== false) {
                    $cleanBase64 = substr($logoBase64, $commaPos + 1);
                    Log::info('🖼️ Prefijo data:image removido');
                } else {
                    Log::warning('⚠️ Prefijo data:image encontrado pero sin coma separadora');
                }
            }

            // ✅ Limpiar espacios y caracteres extra
            $cleanBase64 = trim($cleanBase64);
            $cleanBase64 = str_replace([' ', '\n', '\r', '\t'], '', $cleanBase64);

            Log::info('🖼️ Base64 limpio', [
                'clean_length' => strlen($cleanBase64),
                'preview' => substr($cleanBase64, 0, 50) . '...'
            ]);

            // ✅ Decodificar Base64
            $logoData = base64_decode($cleanBase64, true); // strict mode
            if ($logoData === false || empty($logoData)) {
                Log::error('❌ No se pudo decodificar el logo Base64', [
                    'clean_base64_length' => strlen($cleanBase64),
                    'is_valid_base64' => base64_encode(base64_decode($cleanBase64, true)) === $cleanBase64
                ]);
                return;
            }

            Log::info('🖼️ Base64 decodificado exitosamente', [
                'decoded_size_bytes' => strlen($logoData)
            ]);

            // ✅ Guardar temporalmente con timestamp para evitar conflictos
            $timestamp = time();
            $tempPath = storage_path("app/public/temp_company_logo_{$timestamp}.png");
            $bytesWritten = file_put_contents($tempPath, $logoData);

            Log::info('🖼️ Archivo temporal creado', [
                'path' => $tempPath,
                'bytes_written' => $bytesWritten,
                'file_exists' => file_exists($tempPath)
            ]);

            // ✅ Imprimir logo
            if (file_exists($tempPath) && filesize($tempPath) > 0) {
                $imgLogo = EscposImage::load($tempPath);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);

                Log::info('✅ Logo Base64 impreso correctamente');
            } else {
                Log::error('❌ Archivo temporal no válido o vacío');
            }

            // ✅ Limpiar archivo temporal
            if (file_exists($tempPath)) {
                @unlink($tempPath);
                Log::info('🗑️ Archivo temporal eliminado');
            }
        } catch (\Exception $e) {
            Log::error('❌ Error procesando logo Base64', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            // Limpiar archivo temporal en caso de error
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * 🔗 Imprimir código QR usando mike42/escpos-php nativo
     * Basado en documentación: qrCode($content, $ec, $size, $model)
     */
    private function printQRCode($printer, $qrData)
    {
        try {
            Log::info('🔗 Generando código QR nativo con mike42/escpos-php...');

            // ✅ USAR MÉTODO NATIVO qrCode() de mike42/escpos-php
            // Parámetros según documentación:
            // - $content: string - contenido del QR
            // - $ec: int - nivel de corrección de errores:
            //   * Printer::QR_ECLEVEL_L (7% recovery) - MÁS COMPACTO
            //   * Printer::QR_ECLEVEL_M (15% recovery) - BALANCEADO
            //   * Printer::QR_ECLEVEL_Q (25% recovery) - ALTA CALIDAD
            //   * Printer::QR_ECLEVEL_H (30% recovery) - MÁXIMA CALIDAD
            // - $size: int - tamaño del pixel (1-16, por defecto 3)
            // - $model: int - modelo QR:
            //   * Printer::QR_MODEL_1 - Versión original
            //   * Printer::QR_MODEL_2 - Estándar (recomendado)
            //   * Printer::QR_MICRO - Micro QR (no soportado por todas las impresoras)

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // ✅ QR optimizado para impresoras térmicas 80mm
            $printer->qrCode(
                $qrData,                    // URL DIAN con CUFE
                Printer::QR_ECLEVEL_L,      // Corrección baja = más compacto
                4,                          // Tamaño 4 = pequeño pero legible
                Printer::QR_MODEL_2         // Modelo estándar
            );

            $printer->feed(1);
        } catch (\Exception $e) {
            // Fallback 1: Intentar con parámetros mínimos
            try {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->qrCode($qrData); // Solo contenido, usar defaults
                $printer->feed(1);
            } catch (\Exception $fallbackException) {
                Log::error('❌ Error en QR fallback: ' . $fallbackException->getMessage());

                // Fallback final: imprimir URL como texto
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("QR: " . substr($qrData, 0, 40) . "...\n");
            }
        }
    }

    /**
     * Word wrap mejorado para ESC/POS - Optimizado para papel 58mm
     */
    private function wordWrapEscPos($text, $maxChars)
    {
        if (strlen($text) <= $maxChars) {
            return [$text]; // Si el texto ya cabe, devolver como está
        }

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            // Si la palabra sola es más larga que el ancho máximo, dividirla
            if (strlen($word) > $maxChars) {
                // Finalizar línea actual si tiene contenido
                if ($currentLine) {
                    $lines[] = trim($currentLine);
                    $currentLine = '';
                }

                // Dividir palabra larga en chunks
                $wordChunks = str_split($word, $maxChars);
                foreach ($wordChunks as $chunk) {
                    $lines[] = $chunk;
                }
                continue;
            }

            // Verificar si la palabra cabe en la línea actual
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;

            if (strlen($testLine) <= $maxChars) {
                $currentLine = $testLine;
            } else {
                // No cabe, finalizar línea actual y empezar nueva
                if ($currentLine) {
                    $lines[] = trim($currentLine);
                }
                $currentLine = $word;
            }
        }

        // Agregar última línea si tiene contenido
        if ($currentLine) {
            $lines[] = trim($currentLine);
        }

        return $lines;
    }
}
