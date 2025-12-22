<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    public function openCash(string $name = 'POS-80'): void
    {
        $connector = new WindowsPrintConnector($name);
        $printer = new Printer($connector);

        try {
            $printer->pulse();
        } finally {
            $printer->close();
        }
    }

    public function printOrder(string $printerName, array $orderData, bool $openCash = false): array
    {
        $startTime = microtime(true);

        $connector = new WindowsPrintConnector($printerName);
        $printer = new Printer($connector);

        try {
            $paperWidth = isset($orderData['print_settings']['paper_width']) ? (int) $orderData['print_settings']['paper_width'] : 80;
            $isSmallPaper = $paperWidth == 58;

            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $clientName = $orderData['order_data']['client_name'] ?? $orderData['client_info']['name'] ?? null;

            if ($isSmallPaper) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                if ($clientName && strlen($clientName) > 32) {
                    $clientNameFormatted = substr($clientName, 0, 32);
                } else {
                    $clientNameFormatted = $clientName;
                }
                $printer->text($clientNameFormatted . "\n");
            } else {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                $printer->text($clientName . "\n");
            }

            $printer->selectPrintMode();
            $printer->text($orderData['order_data']['date'] . "\n");

            if (!empty($orderData['order_data']['phone'])) {
                $printer->text("CEL: " . $orderData['order_data']['phone'] . "\n");
            }

            if (!empty($orderData['order_data']['shipping_address'])) {
                $printer->text("DIRECCION: " . $orderData['order_data']['shipping_address'] . "\n");
            }

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $separator = $isSmallPaper ? str_repeat('-', 32) : str_repeat('-', 48);
            $printer->text($separator . "\n");

            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            if ($isSmallPaper) {
                $printer->text("CANT  ITEM\n");
            } else {
                $printer->text("CANT     ITEM\n");
            }
            $printer->selectPrintMode();
            $printer->text($separator . "\n");

            $products = $orderData['products'] ?? [];
            $productCount = count($products);
            $currentIndex = 0;

            foreach ($products as $product) {
                $currentIndex++;
                $qty = $product['quantity'] ?? 1;
                $name = $product['name'] ?? 'Producto';
                $notes = $product['notes'] ?? '';

                // Separar el nombre base de las opciones (ej: toppings) que vengan dentro del mismo nombre
                [$baseName, $nameOptions] = $this->splitProductNameAndOptions($name);

                // Limpiar el nombre base
                $baseName = trim($baseName);
                if (empty($baseName)) {
                    $baseName = 'Producto';
                }

                if ($isSmallPaper) {
                    $qtyPadded = str_pad((string) $qty, 2, ' ', STR_PAD_RIGHT);
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $maxNameChars = 28;
                    $nameFormatted = strlen($baseName) > $maxNameChars ? substr($baseName, 0, $maxNameChars) : $baseName;
                    $printer->text($qtyPadded . "  " . strtoupper($nameFormatted) . "\n");
                    $printer->selectPrintMode();
                    if (strlen($baseName) > $maxNameChars) {
                        $remainingName = substr($baseName, $maxNameChars);
                        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                        $printer->text("    " . strtoupper($remainingName) . "\n");
                        $printer->selectPrintMode();
                    }
                } else {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                    $qtyPadded = str_pad((string) $qty, 2, ' ', STR_PAD_RIGHT);
                    $printer->text($qtyPadded . "  " . strtoupper($baseName) . "\n");
                    $printer->selectPrintMode();
                }

                // Combinar opciones provenientes del nombre + notas del producto
                $combinedNotes = '';
                if (!empty($nameOptions)) {
                    $combinedNotes = trim($nameOptions);
                }
                if (!empty($notes) && $notes !== null && trim($notes) !== '') {
                    if (!empty($combinedNotes)) {
                        $combinedNotes .= ' + ' . trim($notes);
                    } else {
                        $combinedNotes = trim($notes);
                    }
                }

                // Imprimir las notas si existen
                if (!empty($combinedNotes)) {
                    $this->printProductNotes($printer, $combinedNotes, $isSmallPaper);
                }

                if ($currentIndex < $productCount) {
                    $printer->text("\n");
                }
            }

            $printer->text($separator . "\n");

            $generalNote = $orderData['order_data']['note'] ?? $orderData['general_note'] ?? null;
            if (!empty($generalNote) && $generalNote !== null) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("NOTA: " . strtoupper($generalNote) . "\n");
                $printer->selectPrintMode();
                $printer->feed(1);
            }

            $userName = $orderData['user']['name'] ?? $orderData['user']['nickname'] ?? 'Sistema';
            $printer->text("Atendido por: " . $userName . "\n");
            $printer->text("Impresión: " . $orderData['order_data']['date_print'] . "\n");

            $orderIdDisplay = !empty($orderData['order_data']['shipping_address']) ? $orderData['order_data']['order_number'] : ($orderData['order_data']['id'] ?? '1');
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("ORDEN: " . $orderIdDisplay . "\n");
            $printer->selectPrintMode();

            $printer->feed(1);
            $this->triggerPrintAlert($printer);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'message' => 'Orden impresa correctamente con ESC/POS',
                'execution_time_ms' => $executionTime,
                'mode' => 'escpos_optimized',
            ];
        } finally {
            $printer->close();
        }
    }

    public function printSale(string $printerName, string $base64Image, bool $openCash = false, ?string $logoBase64 = null, ?string $logo = null): void
    {
        if (empty($base64Image)) {
            return;
        }

        $imageData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $base64Image));

        $tempPath = storage_path('app/public/temp_image.png');
        file_put_contents($tempPath, $imageData);

        $tempPathLogo = null;
        try {
            if ($logoBase64 && !empty($logoBase64)) {
                $logoData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $logoBase64));
                $tempPathLogo = storage_path('app/public/temp_logo.png');
                file_put_contents($tempPathLogo, $logoData);
            } elseif ($logo && !empty($logo)) {
                $logoHash = md5($logo);
                $cacheDir = storage_path('app/public/logo_cache');
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                $tempPathLogo = $cacheDir . '/company_logo_' . $logoHash . '.png';
                if (!file_exists($tempPathLogo)) {
                    $logoData = @file_get_contents($logo);
                    if ($logoData !== false) {
                        file_put_contents($tempPathLogo, $logoData);
                    } else {
                        $tempPathLogo = null;
                    }
                }
            }

            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            try {
                if ($tempPathLogo && file_exists($tempPathLogo)) {
                    $imgLogo = EscposImage::load($tempPathLogo);
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->bitImage($imgLogo);
                    $printer->feed(1);
                }

                $img = EscposImage::load($tempPath);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($img);
                $printer->feed(1);
                $printer->cut();

                if ($openCash) {
                    $printer->pulse();
                }
            } finally {
                $printer->close();
            }
        } catch (\Exception $e) {
            // swallow and continue cleanup like original behavior
        } finally {
            @unlink($tempPath);
            if ($tempPathLogo && strpos($tempPathLogo, 'logo_') !== false) {
                @unlink($tempPathLogo);
            }
        }
    }

    public function printSaleEscPos(string $printerName, array $saleData, bool $openCash = false, ?array $company = null, ?string $logoBase64 = null): void
    {
        $connector = new WindowsPrintConnector($printerName);
        $printer = new Printer($connector);

        try {
            $printer->initialize();
            $this->printCompanyHeader($printer, $company, $logoBase64);
            $this->printSaleInfo($printer, $saleData);
            $this->printProducts($printer, $saleData);
            $this->printTotals($printer, $saleData);
            $this->printAdditionalInfo($printer, $saleData);
            $this->printFooter($printer, $saleData);

            $printer->feed(2);
            $this->triggerPrintAlert($printer);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
            }
        } finally {
            $printer->close();
        }
    }

    private function printCompanyHeader($printer, ?array $company, ?string $logoBase64): void
    {
        try {
            if ($company) {
                $companyName = $company['name'] ?? $company['business_name'] ?? '';
                $companyAddress = $company['address'] ?? '';
                $companyPhone = $company['phone'] ?? '';
                $companyNit = $company['nit'] ?? '';
            }

            if (!empty($logoBase64) && $logoBase64 !== 'null' && trim($logoBase64) !== '') {
                $this->printCompanyLogo($printer, $logoBase64);
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (!empty($companyName)) {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
                $printer->text(strtoupper($this->normalizeText($companyName)) . "\n");
                $printer->selectPrintMode();
            }

            if (!empty($companyAddress)) {
                $printer->text("DIRECCION: " . strtoupper($this->normalizeText($companyAddress)) . "\n");
            }

            if (!empty($companyPhone)) {
                $printer->text("CELULAR: " . $this->normalizeText($companyPhone) . "\n");
            }

            if (!empty($companyNit)) {
                $printer->text("NIT: " . $this->normalizeText($companyNit) . "\n");
            }
        } catch (\Exception $e) {
            Log::error('Error en encabezado de empresa', ['error' => $e->getMessage()]);
        }
    }

    private function printSaleInfo($printer, array $saleData): void
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_RIGHT);

            $billing = $saleData['billing'] ?? '';
            if (!empty($billing)) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("VENTA: " . $this->normalizeText($billing) . "\n");
                $printer->selectPrintMode();
            }

            $client = $saleData['client'] ?? [];
            if (!empty($client)) {
                $firstName = $client['first_name'] ?? '';
                $firstSurname = $client['first_surname'] ?? '';
                $clientName = trim($firstName . ' ' . $firstSurname);

                if (!empty($clientName)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("CLIENTE: " . strtoupper($this->normalizeText($clientName)) . "\n");
                    $printer->selectPrintMode();
                }

                $document = $client['document'] ?? '';
                if (!empty($document)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("DOCUMENTO: " . $this->normalizeText($document) . "\n");
                    $printer->selectPrintMode();
                }
            }

            $printer->feed(1);
        } catch (\Exception $e) {
            Log::error('Error en información de venta', ['error' => $e->getMessage()]);
        }
    }

    private function printProducts($printer, array $saleData): void
    {
        try {
            $itemsDetail = $saleData['items_detail'] ?? [];
            if (empty($itemsDetail)) {
                return;
            }

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("ITEM                        CANT      VALOR\n");
            $printer->selectPrintMode();
            $printer->text(str_repeat('-', 48) . "\n");

            foreach ($itemsDetail as $item) {
                $product = $item['product'] ?? [];
                $productName = $product['name'] ?? 'Producto';
                $quantity = $item['quantity'] ?? 1;
                $totalValue = $item['total_value'] ?? 0;
                $notes = $item['note'] ?? '';

                $nameNormalized = $this->normalizeText($productName);
                $nameTruncated = strlen($nameNormalized) > 28 ? substr($nameNormalized, 0, 28) : $nameNormalized;

                $line = sprintf("%-28s %4d %12s", strtoupper($nameTruncated), $quantity, $this->formatCurrency($totalValue));
                $printer->text($line . "\n");

                if (!empty($notes) && $notes !== null) {
                    $printer->text(" * " . strtoupper($this->normalizeText($notes)) . "\n");
                }
            }

            $printer->text(str_repeat('-', 48) . "\n");
        } catch (\Exception $e) {
            Log::error('Error en productos', ['error' => $e->getMessage()]);
        }
    }

    private function printTotals($printer, array $saleData): void
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $subTotal = $saleData['sub_total'] ?? 0;
            $totalTaxValue = $saleData['total_tax_value'] ?? 0;
            $totalValue = $saleData['total_value'] ?? 0;
            $totalTip = $saleData['total_tip'] ?? 0;
            $discount = $saleData['discount'] ?? 0;

            if (($subTotal - $totalTaxValue != $totalValue) || $totalTip > 0) {
                if ($subTotal > 0) {
                    $printer->text(sprintf("SUBTOTAL                     %s\n", $this->formatCurrency($subTotal - $totalTaxValue)));
                }
            }

            if ($discount > 0) {
                $printer->text(sprintf("DESCUENTO                   -%s\n", $this->formatCurrency($discount)));
            }

            if ($totalTaxValue > 0) {
                $printer->text(sprintf("IMPUESTO                     %s\n", $this->formatCurrency($totalTaxValue)));
            }

            if ($totalTip > 0) {
                $printer->text(sprintf("PROPINA                      %s\n", $this->formatCurrency($totalTip)));
            }

            $finalTotal = $totalValue + $totalTip;
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->text("TOTAL " . $this->formatCurrency($finalTotal) . "\n");
            $printer->selectPrintMode();
            $printer->feed(1);
        } catch (\Exception $e) {
            Log::error('Error en totales', ['error' => $e->getMessage()]);
        }
    }

    private function printAdditionalInfo($printer, array $saleData): void
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $observation = $saleData['observation'] ?? '';
            if (!empty($observation) && $observation !== null) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Nota: " . strtoupper($this->normalizeText($observation)) . "\n");
                $printer->selectPrintMode();
            }

            $deliveryOrder = $saleData['delivery_order'] ?? null;
            if (!empty($deliveryOrder)) {
                $shippingAddress = $deliveryOrder['shipping_address'] ?? '';
                if (!empty($shippingAddress)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Direccion: " . $this->normalizeText($shippingAddress) . "\n");
                    $printer->selectPrintMode();
                }

                $phone = $deliveryOrder['phone'] ?? '';
                if (!empty($phone)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Celular: " . $this->normalizeText($phone) . "\n");
                    $printer->selectPrintMode();
                }

                $clientName = $deliveryOrder['client_name'] ?? '';
                if (!empty($clientName)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Referencia: " . $this->normalizeText($clientName) . "\n");
                    $printer->selectPrintMode();
                }
            }

            $tableOrder = $saleData['table_order'] ?? null;
            if (!empty($tableOrder)) {
                $tableName = $tableOrder['table']['name'] ?? '';
                $tableNumber = $tableOrder['table']['table_number'] ?? '';
                if (!empty($tableName) && !empty($tableNumber)) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text($this->normalizeText($tableName) . ": " . $this->normalizeText($tableNumber) . "\n");
                    $printer->selectPrintMode();
                }
            }

            $paymentMethods = $saleData['payment_methods'] ?? [];
            if (!empty($paymentMethods)) {
                if (count($paymentMethods) == 1) {
                    $method = $paymentMethods[0];
                    $methodName = $method['name'] ?? '';
                    if (!empty($methodName)) {
                        $printer->setJustification(Printer::JUSTIFY_RIGHT);
                        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                        $printer->text("Forma de pago: " . $this->normalizeText($methodName) . "\n");
                        $printer->selectPrintMode();
                    }
                } else {
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $printer->text("Formas de pago:\n");
                    $printer->selectPrintMode();

                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    foreach ($paymentMethods as $method) {
                        $methodName = $method['name'] ?? '';
                        $amount = $method['pivot']['amount'] ?? 0;
                        if (!empty($methodName)) {
                            $printer->text($this->normalizeText($methodName) . ": " . $this->formatCurrency($amount) . "\n");
                        }
                    }
                }
            }

            $quotas = $saleData['quotas'] ?? [];
            if (!empty($quotas)) {
                $printer->feed(1);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Cuotas:\n");
                $printer->selectPrintMode();

                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("NUMERO   FECHA        VALOR\n");
                $printer->selectPrintMode();
                $printer->text(str_repeat('-', 48) . "\n");

                foreach ($quotas as $quota) {
                    $number = $quota['number'] ?? '';
                    $date = $quota['date'] ?? '';
                    $value = $quota['value'] ?? 0;
                    $quotaLine = sprintf("%-8s %-12s %12s", $number, $date, $this->formatCurrency($value));
                    $printer->text($quotaLine . "\n");
                }

                $printer->feed(1);
                $printer->text($this->normalizeText("Esta factura constituye título valor según Ley 1231/2008 de Colombia.\n"));
                $printer->text($this->normalizeText("El cliente se compromete a pagar según fechas acordadas.\n"));
                $printer->feed(1);
                $printer->text("Firma: _____________________________\n");
                $printer->text("ID: ___________________\n");
                $printer->feed(1);
            }
        } catch (\Exception $e) {
            Log::error('Error en información adicional', ['error' => $e->getMessage()]);
        }
    }

    private function printFooter($printer, array $saleData): void
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_RIGHT);

            $user = $saleData['user'] ?? [];
            $userName = $user['nickname'] ?? $user['name'] ?? '';
            if (!empty($userName)) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Atendido por: " . $this->normalizeText($userName) . "\n");
                $printer->selectPrintMode();
            }

            $createdAt = $saleData['created_at'] ?? '';
            if (!empty($createdAt)) {
                $date = date('d/m/Y h:i:s A', strtotime($createdAt) - 18000);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("Generacion: " . $date . "\n");
                $printer->selectPrintMode();
            }

            $printer->feed(1);

            $configResolution = $saleData['config_resolution'] ?? [];
            $note = $configResolution['note'] ?? '';
            if (!empty($note) && $note !== 'null') {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($this->normalizeText($note) . "\n");
                $printer->feed(1);
            }

            $cufe = $saleData['cufe'] ?? '';
            if (empty($cufe) || $cufe === 'null') {
                $invoiceSents = $saleData['invoice_sents'] ?? [];
                if (!empty($invoiceSents)) {
                    $cufe = $invoiceSents[0]['cufe'] ?? '';
                }
            }

            if (!empty($cufe) && $cufe !== 'null' && strtolower($cufe) !== 'null') {
                $qrUrl = "https://catalogo-vpfe.dian.gov.co/User/SearchDocument?documentkey=" . $cufe;
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                $printer->text("CUFE:\n");
                $printer->selectPrintMode();
                $this->printQRCode($printer, $qrUrl);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($cufe . "\n");
                $printer->feed(1);
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($this->normalizeText("¡Gracias por tu compra!\n"));
            $printer->feed(1);
            $currentYear = date('Y');
            $printer->text("GridPOS $currentYear\n");
        } catch (\Exception $e) {
            Log::error('Error en pie de página', ['error' => $e->getMessage()]);
        }
    }

    private function formatCurrency($amount): string
    {
        try {
            if ($amount == (int) $amount) {
                return '$ ' . number_format($amount, 0, ',', ',');
            }
            return '$ ' . number_format($amount, 2, ',', ',');
        } catch (\Exception $e) {
            return '$ ' . number_format((float) $amount, 0);
        }
    }

    private function normalizeText($text): string
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
        ], trim((string) $text));
    }

    private function printCompanyLogo($printer, string $logoBase64): void
    {
        try {
            $cleanBase64 = $logoBase64;
            if (strpos($logoBase64, 'data:image') === 0) {
                $commaPos = strpos($logoBase64, ',');
                if ($commaPos !== false) {
                    $cleanBase64 = substr($logoBase64, $commaPos + 1);
                } else {
                    Log::warning('Prefijo data:image encontrado pero sin coma separadora');
                }
            }

            $cleanBase64 = trim($cleanBase64);
            $cleanBase64 = str_replace([' ', '\n', '\r', '\t'], '', $cleanBase64);

            $logoData = base64_decode($cleanBase64, true);
            if ($logoData === false || empty($logoData)) {
                Log::error('No se pudo decodificar el logo Base64', [
                    'clean_base64_length' => strlen($cleanBase64),
                    'is_valid_base64' => base64_encode(base64_decode($cleanBase64, true)) === $cleanBase64,
                ]);
                return;
            }

            $timestamp = time();
            $tempPath = storage_path("app/public/temp_company_logo_{$timestamp}.png");
            file_put_contents($tempPath, $logoData);

            if (file_exists($tempPath) && filesize($tempPath) > 0) {
                $imgLogo = EscposImage::load($tempPath);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);
            } else {
                Log::error('Archivo temporal de logo no válido o vacío');
            }

            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        } catch (\Exception $e) {
            Log::error('Error procesando logo Base64', [
                'error' => $e->getMessage(),
            ]);
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function printQRCode($printer, string $qrData): void
    {
        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->qrCode($qrData, Printer::QR_ECLEVEL_L, 4, Printer::QR_MODEL_2);
            $printer->feed(1);
        } catch (\Exception $e) {
            try {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->qrCode($qrData);
                $printer->feed(1);
            } catch (\Exception $fallbackException) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("QR: " . substr($qrData, 0, 40) . "...\n");
            }
        }
    }

    /**
     * Separa el nombre base del producto de las opciones que vienen dentro del mismo nombre.
     *
     * Ejemplos:
     *  - "MICHELADAS — ÁGUILA | SAL MICHELADA, PIMIENTA" =>
     *      ["MICHELADAS — ÁGUILA", "SAL MICHELADA, PIMIENTA"]
     *  - "VASO MEDIANO 12 ONZ - ZUCARITA 70 ML, FROOT LOOPS 70 ML, PIAZZA | FRESA, MORA" =>
     *      ["VASO MEDIANO 12 ONZ - ZUCARITA 70 ML, FROOT LOOPS 70 ML, PIAZZA", "FRESA, MORA"]
     */
    private function splitProductNameAndOptions(string $name): array
    {
        if (trim($name) === '') {
            return ['Producto', ''];
        }

        // Normalizar espacios
        $cleanName = preg_replace('/\s+/', ' ', trim($name));

        // Regla 1: si hay un pipe, asumimos que todo lo que está después del último "|"
        // son opciones (toppings, sabores, etc.)
        // Buscar el último pipe que no esté al inicio
        $lastPipePos = strrpos($cleanName, '|');
        if ($lastPipePos !== false && $lastPipePos > 0) {
            $base = trim(substr($cleanName, 0, $lastPipePos));
            $options = trim(substr($cleanName, $lastPipePos + 1));
            // Solo retornar si hay contenido en ambas partes
            if (!empty($base) && !empty($options)) {
                return [$base, $options];
            }
        }

        // Regla 2: si no hay pipe pero hay "+", usamos el último "+"
        $lastPlusPos = strrpos($cleanName, '+');
        if ($lastPlusPos !== false && $lastPlusPos > 0) {
            $base = trim(substr($cleanName, 0, $lastPlusPos));
            $options = trim(substr($cleanName, $lastPlusPos + 1));
            // Solo retornar si hay contenido en ambas partes
            if (!empty($base) && !empty($options)) {
                return [$base, $options];
            }
        }

        // Regla 3: si no hay separadores especiales, todo es nombre base
        return [$cleanName, ''];
    }

    private function printProductNotes($printer, string $notes, bool $isSmallPaper): void
    {
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);

        // Separar las notas por comas, pipes y símbolo +, limpiar y filtrar vacíos
        $noteItems = preg_split('/[,|+]/', $notes);
        $noteItems = array_map(function ($item) {
            // Limpiar espacios extra, saltos de línea y caracteres no deseados
            $cleaned = trim($item);
            $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Reemplazar múltiples espacios por uno solo
            return $cleaned;
        }, $noteItems);

        $noteItems = array_filter($noteItems, function ($item) {
            return !empty($item) && trim($item) !== '';
        });

        if (empty($noteItems)) {
            $printer->selectPrintMode();
            return;
        }

        // Ancho máximo considerando el indent y el prefijo "* "
        $paperWidth = $isSmallPaper ? 32 : 48;
        $indent = $isSmallPaper ? "  " : "    ";
        $prefix = $indent . "* ";
        $separator = " + ";
        $maxNoteChars = $paperWidth - strlen($prefix); // Ancho disponible para el contenido

        // Agrupar elementos en líneas eficientes
        $currentLine = "";
        $isFirstItem = true;

        foreach ($noteItems as $noteItem) {
            $noteItem = trim($noteItem);
            if (empty($noteItem)) {
                continue;
            }

            $noteItemUpper = strtoupper($noteItem);

            // Si es el primer elemento de la línea
            if ($isFirstItem) {
                $testLine = $noteItemUpper;
            } else {
                $testLine = $currentLine . $separator . $noteItemUpper;
            }

            // Si la línea completa cabe, agregar el elemento
            if (strlen($testLine) <= $maxNoteChars) {
                $currentLine = $testLine;
                $isFirstItem = false;
            } else {
                // La línea no cabe, imprimir la línea actual si tiene contenido
                if (!empty($currentLine)) {
                    $printer->text($prefix . $currentLine . "\n");
                }

                // Si el elemento individual es muy largo, hacer word wrap
                if (strlen($noteItemUpper) > $maxNoteChars) {
                    $wrappedLines = $this->wordWrapEscPos($noteItemUpper, $maxNoteChars);
                    foreach ($wrappedLines as $index => $line) {
                        $linePrefix = $index === 0 ? $prefix : $indent . "  ";
                        $printer->text($linePrefix . trim($line) . "\n");
                    }
                    $currentLine = "";
                    $isFirstItem = true;
                } else {
                    // Empezar nueva línea con este elemento
                    $currentLine = $noteItemUpper;
                    $isFirstItem = false;
                }
            }
        }

        // Imprimir la última línea si tiene contenido
        if (!empty($currentLine)) {
            $printer->text($prefix . $currentLine . "\n");
        }

        $printer->selectPrintMode();
    }

    private function wordWrapEscPos(string $text, int $maxChars): array
    {
        if (strlen($text) <= $maxChars) {
            return [$text];
        }

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }

            // Si una palabra es más larga que el máximo, dividirla
            if (strlen($word) > $maxChars) {
                if ($currentLine) {
                    $lines[] = trim($currentLine);
                    $currentLine = '';
                }
                // Dividir la palabra larga
                $wordChunks = str_split($word, $maxChars);
                foreach ($wordChunks as $chunk) {
                    $lines[] = $chunk;
                }
                continue;
            }

            // Intentar agregar la palabra a la línea actual
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;
            if (strlen($testLine) <= $maxChars) {
                $currentLine = $testLine;
            } else {
                // La línea está llena, guardarla y empezar nueva
                if ($currentLine) {
                    $lines[] = trim($currentLine);
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = trim($currentLine);
        }

        return $lines;
    }

    private function triggerPrintAlert(Printer $printer, int $times = 1, int $durationMs = 120): void
    {
        try {
            $connector = $printer->getPrintConnector();
            $times = max(1, $times);
            for ($i = 0; $i < $times; $i++) {
                $connector->write("\x07");
                if ($durationMs > 0) {
                    usleep($durationMs * 1000);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo reproducir el beep de alerta', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
