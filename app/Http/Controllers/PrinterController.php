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
        Log::info('Abriendo caja: ' . $name);
        $connector = new WindowsPrintConnector($name);
        $printer = new Printer($connector);

        try {
            $printer->pulse();
            $printer->close();
            Log::info('Caja abierta con éxito: ' . $name);
            return response()->json(['message' => 'Caja abierta'], 200);
        } catch (\Exception $e) {
            Log::error('Error al abrir la caja: ' . $e->getMessage());
            return response()->json(['message' => 'Error al abrir la caja', 'error' => $e->getMessage()], 500);
        }
    }

    public function printOrder(Request $request)
    {
        ini_set('memory_limit', '1024M');

        Log::info('Iniciando impresión de orden en: ' . ($request->printerName ?? 'impresora no especificada'));
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $useJsonMode = $request->useJsonMode ?? false;

        // Verificar el modo de impresión
        if ($useJsonMode) {
            // 🚀 MODO OPTIMIZADO: Usar comandos ESC/POS directos
            Log::info('🚀 Modo ESC/POS OPTIMIZADO activado - Enviando comandos nativos');
            $orderData = $request->orderData;

            if (empty($orderData)) {
                Log::error('Error: Datos de orden no proporcionados para modo ESC/POS');
                return response()->json(['message' => 'Error: Datos de orden no proporcionados'], 400);
            }

            return $this->printOrderWithEscPos($printerName, $orderData, $openCash);
        } else {
            // Modo tradicional: usar imagen base64 (compatibilidad)
            Log::info('🐌 Modo tradicional activado - Usando imagen base64 (lento)');
            $base64Image = $request->input('image');

            if (empty($base64Image)) {
                Log::error('Error: Imagen no proporcionada para printOrder');
                return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
            }

            return $this->printOrderWithImage($printerName, $base64Image, $openCash);
        }
    }

    /**
     * 🚀 MÉTODO OPTIMIZADO: Imprimir orden usando comandos ESC/POS nativos
     * ULTRA RÁPIDO - Sin generación de imágenes
     */
    private function printOrderWithEscPos($printerName, $orderData, $openCash = false)
    {
        try {
            $startTime = microtime(true);

            // Crear conexión directa con la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Configurar papel según ancho
            $paperWidth = $orderData['print_settings']['paper_width'] ?? 80;
            $isSmallPaper = $paperWidth == 58;

            // === ENCABEZADO ===
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // Nombre de la empresa (EXTRA GRANDE)
            $companyName = $orderData['company_info']['name'] ?? 'RESTAURANTE';
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->text($companyName . "\n");
            $printer->selectPrintMode(); // Reset
            $printer->feed(1);

            // Tipo de orden - MÁS GRANDE
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
            $printer->text("ESCALERA ARRIBA #1\n");
            $printer->selectPrintMode(); // Reset
            $printer->feed(1);

            // Fecha y hora más grande
            $dateFormatted = $this->formatOrderDate($orderData['order_data']['date'] ?? date('c'));
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text($dateFormatted . "\n");
            $printer->selectPrintMode(); // Reset
            $printer->feed(1);

            // Cliente si existe
            $clientName = $orderData['order_data']['client_name'] ?? $orderData['client_info']['name'] ?? null;
            if ($clientName && $clientName !== 'CLIENTE') {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text(strtoupper($clientName) . "\n");
                $printer->selectPrintMode(); // Reset
                $printer->feed(1);
            }

            // === SEPARADOR GRUESO ===
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $separator = $isSmallPaper ? str_repeat('-', 32) : str_repeat('-', 48);
            $printer->text($separator . "\n");

            // ENCABEZADOS DE COLUMNAS - MÁS GRANDES
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("CANT         ITEM\n");
            $printer->selectPrintMode(); // Reset
            $printer->text($separator . "\n");

            // === PRODUCTOS - FORMATO MEJORADO ===
            $products = $orderData['products'] ?? [];
            foreach ($products as $product) {
                $qty = $product['quantity'] ?? 1;
                $name = $product['name'] ?? 'Producto';
                $notes = $product['notes'] ?? '';

                // Línea del producto con formato más grande
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $qtyPadded = str_pad($qty, 3, ' ', STR_PAD_RIGHT);
                $printer->text($qtyPadded . "      " . strtoupper($name) . "\n");
                $printer->selectPrintMode(); // Reset

                // Notas del producto si existen (indentadas y más visibles)
                if (!empty($notes) && $notes !== null) {
                    $printer->text("         * " . strtoupper($notes) . "\n");
                }

                $printer->feed(1); // Más espacio entre productos
            }

            // === SEPARADOR FINAL ===
            $printer->text($separator . "\n");
            $printer->feed(1);

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
            $printer->text("Impresión: " . date('d/m/Y H:i:s') . "\n");

            // ID de orden más visible
            $orderIdDisplay = $orderData['order_data']['id'] ?? '1';
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("ORDEN: " . $orderIdDisplay . "\n");
            $printer->selectPrintMode(); // Reset

            $printer->feed(3);
            $printer->cut();

            // Abrir caja si se requiere
            if ($openCash) {
                $printer->pulse();
                Log::info('Caja abierta como parte del proceso de impresión ESC/POS');
            }

            $printer->close();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("🚀 Orden impresa con ESC/POS en {$executionTime}ms (ULTRA RÁPIDO) en: " . $printerName);

            return response()->json([
                'message' => 'Orden impresa correctamente con ESC/POS',
                'execution_time_ms' => $executionTime,
                'mode' => 'escpos_optimized'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir con ESC/POS: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al imprimir la orden con ESC/POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🐌 MÉTODO TRADICIONAL: Imprimir orden usando imagen (lento - solo compatibilidad)
     */
    private function printOrderWithImage($printerName, $base64Image, $openCash = false)
    {
        try {
            $startTime = microtime(true);

            // Decodificar el string base64 para obtener los datos binarios de la imagen
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

            // Guardar temporalmente la imagen decodificada
            $tempPath = storage_path('app/public/temp_image_' . uniqid() . '.png');
            file_put_contents($tempPath, $imageData);

            $result = $this->printImageToDevice($printerName, $tempPath, $openCash);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("🐌 Orden impresa con imagen en {$executionTime}ms (LENTO) en: " . $printerName);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error al imprimir con imagen: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al imprimir la orden con imagen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🖼️ MÉTODO PARA IMPRIMIR IMAGEN EN DISPOSITIVO
     * Método que faltaba para el modo tradicional de imagen
     */
    private function printImageToDevice($printerName, $imagePath, $openCash = false)
    {
        try {
            // Crear conexión con la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar la imagen
            $img = EscposImage::load($imagePath);

            // Configurar centrado y imprimir imagen
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(2);
            $printer->cut();

            // Abrir caja si se requiere
            if ($openCash) {
                $printer->pulse();
                Log::info('Caja abierta como parte de la impresión de imagen');
            }

            $printer->close();

            // Eliminar archivo temporal
            @unlink($imagePath);

            Log::info('Imagen impresa correctamente en: ' . $printerName);
            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            // Eliminar archivo temporal en caso de error
            @unlink($imagePath);

            Log::error('Error al imprimir imagen: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al imprimir la imagen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function printSale(Request $request)
    {
        ini_set('memory_limit', '1024M');

        Log::info('Iniciando impresión de venta en: ' . ($request->printerName ?? 'impresora no especificada'));
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $useJsonMode = $request->useJsonMode ?? false;

        // Verificar el modo de impresión
        if ($useJsonMode) {
            // 🚀 MODO OPTIMIZADO: Usar comandos ESC/POS directos para venta
            Log::info('🚀 Modo ESC/POS OPTIMIZADO activado para venta - Enviando comandos nativos');
            $saleData = $request->saleData;

            if (empty($saleData)) {
                Log::error('Error: Datos de venta no proporcionados para modo ESC/POS');
                return response()->json(['message' => 'Error: Datos de venta no proporcionados'], 400);
            }

            return $this->printSaleWithEscPos($printerName, $saleData, $openCash);
        } else {
            // Modo tradicional: usar imagen base64 (compatibilidad)
            Log::info('🐌 Modo tradicional activado para venta - Usando imagen base64 (lento)');
            $base64Image = $request->input('image');
            $logoBase64 = $request->input('logoBase64');

            if (empty($base64Image)) {
                Log::error('Error: Imagen no proporcionada para printSale');
                return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
            }

            return $this->printSaleWithImage($printerName, $base64Image, $logoBase64, $openCash);
        }
    }

    /**
     * 🚀 MÉTODO OPTIMIZADO: Imprimir venta usando comandos ESC/POS nativos
     * ULTRA RÁPIDO - Sin generación de imágenes
     */
    private function printSaleWithEscPos($printerName, $saleData, $openCash = false)
    {
        try {
            $startTime = microtime(true);

            // Crear conexión directa con la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Configurar papel según ancho
            $paperWidth = $saleData['print_settings']['paper_width'] ?? 80;
            $isSmallPaper = $paperWidth == 58;

            // === ENCABEZADO ===
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // Logo o nombre de la empresa (grande y centrado)
            $companyName = $saleData['company_info']['name'] ?? 'EMPRESA';
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->text($companyName . "\n");
            $printer->selectPrintMode(); // Reset

            // Información de la empresa si existe
            if (!empty($saleData['company_info']['address'])) {
                $printer->text($saleData['company_info']['address'] . "\n");
            }
            if (!empty($saleData['company_info']['phone'])) {
                $printer->text("Tel: " . $saleData['company_info']['phone'] . "\n");
            }
            if (!empty($saleData['company_info']['nit'])) {
                $printer->text("NIT: " . $saleData['company_info']['nit'] . "\n");
            }

            $printer->feed(1);

            // FACTURA DE VENTA
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("FACTURA DE VENTA\n");
            $printer->selectPrintMode(); // Reset

            // Número de factura y fecha
            $invoiceNumber = $saleData['sale_data']['invoice_number'] ?? 'FAC-' . ($saleData['sale_data']['id'] ?? '1');
            $printer->text("No: " . $invoiceNumber . "\n");

            $dateFormatted = $this->formatOrderDate($saleData['sale_data']['date'] ?? date('c'));
            $printer->text($dateFormatted . "\n");

            $printer->feed(1);

            // Información del cliente
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            if (!empty($saleData['client_info']['name'])) {
                $printer->text("Cliente: " . $saleData['client_info']['name'] . "\n");
            }
            if (!empty($saleData['client_info']['document'])) {
                $printer->text("CC/NIT: " . $saleData['client_info']['document'] . "\n");
            }
            if (!empty($saleData['client_info']['address'])) {
                $printer->text("Dir: " . $saleData['client_info']['address'] . "\n");
            }

            $printer->feed(1);

            // === SEPARADOR ===
            $separator = $isSmallPaper ? str_repeat('-', 32) : str_repeat('-', 48);
            $printer->text($separator . "\n");

            // Headers de productos
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            if ($isSmallPaper) {
                $printer->text("CANT ITEM              VALOR\n");
            } else {
                $printer->text("CANT ITEM                      VALOR\n");
            }
            $printer->selectPrintMode(); // Reset
            $printer->text($separator . "\n");

            // === PRODUCTOS ===
            $products = $saleData['products'] ?? [];
            $subtotal = 0;

            foreach ($products as $product) {
                $qty = $product['quantity'] ?? 1;
                $name = $product['name'] ?? 'Producto';
                $price = floatval($product['price'] ?? 0);
                $total = $qty * $price;
                $subtotal += $total;

                // Línea del producto
                $qtyStr = str_pad($qty, 3, ' ', STR_PAD_LEFT);
                $priceStr = str_pad('$' . number_format($total, 0), 8, ' ', STR_PAD_LEFT);

                if ($isSmallPaper) {
                    // Para 58mm: formato compacto
                    $maxNameChars = 18;
                    $nameStr = strlen($name) > $maxNameChars ? substr($name, 0, $maxNameChars) : str_pad($name, $maxNameChars);
                    $printer->text($qtyStr . " " . $nameStr . " " . $priceStr . "\n");
                } else {
                    // Para 80mm: formato extendido
                    $maxNameChars = 28;
                    $nameStr = strlen($name) > $maxNameChars ? substr($name, 0, $maxNameChars) : str_pad($name, $maxNameChars);
                    $printer->text($qtyStr . " " . $nameStr . " " . $priceStr . "\n");
                }

                // Notas del producto si existen
                if (!empty($product['notes'])) {
                    $printer->text("    * " . $product['notes'] . "\n");
                }
            }

            // === TOTALES ===
            $printer->text($separator . "\n");

            // Calcular totales
            $tax = floatval($saleData['sale_data']['tax'] ?? 0);
            $discount = floatval($saleData['sale_data']['discount'] ?? 0);
            $total = $subtotal + $tax - $discount;

            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text("SUBTOTAL: $" . number_format($subtotal, 0) . "\n");

            if ($tax > 0) {
                $printer->text("IVA: $" . number_format($tax, 0) . "\n");
            }

            if ($discount > 0) {
                $printer->text("DESCUENTO: -$" . number_format($discount, 0) . "\n");
            }

            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
            $printer->text("TOTAL: $" . number_format($total, 0) . "\n");
            $printer->selectPrintMode(); // Reset

            // Forma de pago
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $paymentMethod = $saleData['sale_data']['payment_method'] ?? 'Efectivo';
            $printer->text("Pago: " . $paymentMethod . "\n");

            if ($paymentMethod === 'Efectivo') {
                $cashReceived = floatval($saleData['sale_data']['cash_received'] ?? $total);
                $change = $cashReceived - $total;

                $printer->text("Recibido: $" . number_format($cashReceived, 0) . "\n");
                if ($change > 0) {
                    $printer->text("Cambio: $" . number_format($change, 0) . "\n");
                }
            }

            $printer->feed(1);

            // === PIE DE PÁGINA ===
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("¡GRACIAS POR SU COMPRA!\n");

            // Usuario que atiende
            $userName = $saleData['user']['name'] ?? 'Sistema';
            $printer->text("Atendido por: " . $userName . "\n");

            // Timestamp de impresión
            $printer->text("Impreso: " . date('Y-m-d H:i:s') . "\n");

            $printer->feed(2);
            $printer->cut();

            // Abrir caja si se requiere
            if ($openCash) {
                $printer->pulse();
                Log::info('Caja abierta como parte del proceso de impresión de venta ESC/POS');
            }

            $printer->close();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("🚀 Venta impresa con ESC/POS en {$executionTime}ms (ULTRA RÁPIDO) en: " . $printerName);

            return response()->json([
                'message' => 'Venta impresa correctamente con ESC/POS',
                'execution_time_ms' => $executionTime,
                'mode' => 'escpos_optimized'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir venta con ESC/POS: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al imprimir la venta con ESC/POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🐌 MÉTODO TRADICIONAL: Imprimir venta usando imagen (lento - solo compatibilidad)
     */
    private function printSaleWithImage($printerName, $base64Image, $logoBase64, $openCash = false)
    {
        try {
            $startTime = microtime(true);

            // Decodificar el string base64
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

            // Guardar imagen temporal
            $tempPath = storage_path('app/public/temp_image_' . uniqid() . '.png');
            file_put_contents($tempPath, $imageData);

            // Procesar logo si existe
            $tempPathLogo = null;
            if ($logoBase64) {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoBase64));
                $tempPathLogo = storage_path('app/public/temp_logo_' . uniqid() . '.png');
                file_put_contents($tempPathLogo, $logoData);
                Log::info('Logo procesado correctamente');
            }

            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar y mostrar logo si está presente
            if ($tempPathLogo) {
                $imgLogo = EscposImage::load($tempPathLogo);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);
            }

            // Cargar y mostrar imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(1);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
                Log::info('Caja abierta como parte de la impresión de venta');
            }

            $printer->close();

            // Eliminar archivos temporales
            @unlink($tempPath);
            if ($tempPathLogo) {
                @unlink($tempPathLogo);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("🐌 Venta impresa con imagen en {$executionTime}ms (LENTO) en: " . $printerName);

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la venta: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la factura', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Word wrap para ESC/POS
     */
    private function wordWrapEscPos($text, $maxChars)
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $maxChars) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Formatear fecha para orden
     */
    private function formatOrderDate($dateString)
    {
        try {
            $date = new \DateTime($dateString);
            return $date->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return date('d/m/Y H:i:s');
        }
    }
}
