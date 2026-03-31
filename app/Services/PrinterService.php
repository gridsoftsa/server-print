<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    private const ALERT_PROFILE_DEFAULT = 'generic';
    private const ALERT_PROFILE_SAT_Q22 = 'sat_q22';

    public function openCash(string $name = 'POS-80'): void
    {
        Log::info('✅ Open Cash received');

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
        Log::info('✅ Print Order started');

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

                // Agregar separador entre productos (sin ocupar espacio extra)
                if ($currentIndex < $productCount) {
                    $separatorLine = $isSmallPaper ? str_repeat('-', 32) : str_repeat('-', 48);
                    $printer->text($separatorLine . "\n");
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
            $this->triggerPrintAlert(
                $printer,
                $printerName,
                $orderData['print_settings'] ?? null
            );
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
        Log::info('✅ Print Sale started');

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
        Log::info('✅ Print Sale EscPos started');

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
                $nameCompany = isset($client['name_company']) ? trim((string) $client['name_company']) : '';
                $firstName = $client['first_name'] ?? '';
                $firstSurname = $client['first_surname'] ?? '';
                $clientName = !empty($nameCompany)
                    ? $nameCompany
                    : trim($firstName . ' ' . $firstSurname);

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

                // Imprimir modificadores debajo del producto (con cantidad y precio si aplica)
                $modifiers = $item['modifiers'] ?? [];
                if (!empty($modifiers)) {
                    $this->printItemModifiers($printer, $modifiers);
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
     *      ["MICHELADAS", "— ÁGUILA | SAL MICHELADA, PIMIENTA"]
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

        // Buscar el pipe "|" primero (es el separador principal)
        $pipePos = strpos($cleanName, '|');

        // Si hay pipe, separar ahí (el nombre base es lo anterior, opciones lo posterior)
        if ($pipePos !== false && $pipePos > 0) {
            $basePart = substr($cleanName, 0, $pipePos);
            $options = trim(substr($cleanName, $pipePos + 1));

            // Si el nombre base contiene un guión, separar también ahí
            // Ejemplo: "MICHELADAS — 3x POKER | SAL..." => base: "MICHELADAS", options: "3x POKER, SAL..."
            // Buscar diferentes tipos de guiones - usar funciones que manejen UTF-8 correctamente
            $dashLongPos = mb_strpos($basePart, '—', 0, 'UTF-8');
            $dashMediumPos = mb_strpos($basePart, '–', 0, 'UTF-8');
            $dashNormalPos = strpos($basePart, ' - ');
            $dashSimplePos = strpos($basePart, '-');

            $dashPos = false;
            $dashLength = 0;
            $dashChar = '';

            // Priorizar guión largo, luego medio, luego normal con espacios, luego simple
            if ($dashLongPos !== false && $dashLongPos > 0) {
                $dashPos = $dashLongPos;
                $dashChar = '—';
            } elseif ($dashMediumPos !== false && $dashMediumPos > 0) {
                $dashPos = $dashMediumPos;
                $dashChar = '–';
            } elseif ($dashNormalPos !== false && $dashNormalPos > 0) {
                $dashPos = $dashNormalPos;
                $dashLength = 3; // " - " tiene 3 caracteres
            } elseif ($dashSimplePos !== false && $dashSimplePos > 0) {
                // Verificar que tenga espacios alrededor para evitar falsos positivos
                $charBefore = $dashSimplePos > 0 ? substr($basePart, $dashSimplePos - 1, 1) : '';
                $charAfter = $dashSimplePos < strlen($basePart) - 1 ? substr($basePart, $dashSimplePos + 1, 1) : '';
                if (($charBefore === ' ' && $charAfter === ' ') || ($charBefore === ' ' && $charAfter !== '')) {
                    $dashPos = $dashSimplePos;
                    $dashLength = ($charBefore === ' ') ? 2 : 1; // Incluir el espacio antes si existe
                }
            }

            // Si hay guión en el nombre base, separar ahí
            if ($dashPos !== false && $dashPos > 0) {
                // Usar mb_substr para caracteres UTF-8 cuando sea necesario
                if ($dashChar !== '') {
                    // El guión largo o medio es un carácter UTF-8
                    $base = trim(mb_substr($basePart, 0, $dashPos, 'UTF-8'));
                    // Saltar el carácter del guión (1 carácter) y tomar todo lo que sigue
                    $afterDash = mb_substr($basePart, $dashPos + 1, null, 'UTF-8');
                    $afterDash = trim($afterDash);
                } else {
                    // Guión normal ASCII
                    $base = trim(substr($basePart, 0, $dashPos));
                    $afterDash = trim(substr($basePart, $dashPos + $dashLength));
                }

                // Limpiar espacios extra al inicio pero mantener todo el contenido
                $afterDash = preg_replace('/^\s+/u', '', $afterDash);

                // Asegurarse de que afterDash no esté vacío antes de agregarlo
                if (!empty($afterDash) && trim($afterDash) !== '') {
                    // Concatenar con coma para que printProductNotes lo procese correctamente
                    $options = trim($afterDash) . ', ' . $options;
                }
            } else {
                $base = trim($basePart);
            }

            if (!empty($base) && !empty($options)) {
                return [$base, $options];
            }
        }

        // Si no hay pipe, buscar guiones usando funciones multibyte para UTF-8
        $dashLongPos = mb_strpos($cleanName, '—', 0, 'UTF-8');
        $dashMediumPos = mb_strpos($cleanName, '–', 0, 'UTF-8');
        $dashNormalPos = mb_strpos($cleanName, ' - ', 0, 'UTF-8');
        $dashSimplePos = mb_strpos($cleanName, '-', 0, 'UTF-8');

        $dashPos = false;
        $dashChar = '';

        if ($dashLongPos !== false && $dashLongPos > 0) {
            $dashPos = $dashLongPos;
            $dashChar = '—';
        } elseif ($dashMediumPos !== false && $dashMediumPos > 0) {
            $dashPos = $dashMediumPos;
            $dashChar = '–';
        } elseif ($dashNormalPos !== false && $dashNormalPos > 0) {
            $dashPos = $dashNormalPos;
            $dashChar = ' - ';
        } elseif ($dashSimplePos !== false && $dashSimplePos > 0) {
            $charBefore = $dashSimplePos > 0 ? mb_substr($cleanName, $dashSimplePos - 1, 1, 'UTF-8') : '';
            $charAfter = $dashSimplePos < mb_strlen($cleanName, 'UTF-8') - 1 ? mb_substr($cleanName, $dashSimplePos + 1, 1, 'UTF-8') : '';
            if ($charBefore === ' ' || $charAfter === ' ') {
                $dashPos = $dashSimplePos;
                $dashChar = '-';
            }
        }

        // Si hay un guión, separar ahí usando funciones multibyte
        if ($dashPos !== false && $dashPos > 0) {
            $base = trim(mb_substr($cleanName, 0, $dashPos, 'UTF-8'));
            $options = trim(mb_substr($cleanName, $dashPos + mb_strlen($dashChar, 'UTF-8'), null, 'UTF-8'));
            // Limpiar espacios extra al inicio
            $options = preg_replace('/^\s+/u', '', $options);
            if (!empty($base) && !empty($options)) {
                return [$base, $options];
            }
        }

        // Regla 3: si no hay separadores especiales, todo es nombre base
        return [$cleanName, ''];
    }

    private function printProductNotes($printer, string $notes, bool $isSmallPaper): void
    {
        if (empty(trim($notes))) {
            return;
        }

        // Usar el mismo tamaño que el nombre del producto
        if ($isSmallPaper) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        } else {
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
        }

        // Separar las notas por comas, pipes y símbolo +, limpiar y filtrar vacíos
        $noteItems = preg_split('/[,|+]/', $notes);
        $noteItems = array_map(function ($item) {
            // Limpiar espacios extra, saltos de línea y caracteres no deseados
            $cleaned = trim($item);
            $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Reemplazar múltiples espacios por uno solo
            // Limpiar el guión largo si está al inicio (mantenerlo pero con espacio adecuado)
            $cleaned = preg_replace('/^—\s*/', '— ', $cleaned);
            return $cleaned;
        }, $noteItems);

        $noteItems = array_filter($noteItems, function ($item) {
            $trimmed = trim($item);
            return !empty($trimmed) && $trimmed !== '—' && $trimmed !== '|';
        });

        if (empty($noteItems)) {
            $printer->selectPrintMode();
            return;
        }

        // Ancho máximo considerando el indent y el prefijo "* "
        // Para papel grande con DOUBLE_WIDTH, cada carácter ocupa el doble, así que el ancho efectivo es la mitad
        $paperWidth = $isSmallPaper ? 32 : 48;
        $indent = $isSmallPaper ? " " : "  "; // Reducido para mover el asterisco más a la izquierda
        $prefix = $indent . "* ";
        $separator = " + ";
        // Para papel grande con DOUBLE_WIDTH, dividir el ancho por 2
        $effectiveWidth = $isSmallPaper ? $paperWidth : ($paperWidth / 2);
        $maxNoteChars = (int)($effectiveWidth - strlen($prefix)); // Ancho disponible para el contenido

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

    /**
     * Imprime los modificadores de un ítem debajo del nombre del producto.
     * Muestra cantidad y el valor (precio * cantidad) si el precio es mayor a 0.
     * Mantiene el formato de columnas: nombre (28), cantidad (4), valor (12).
     */
    private function printItemModifiers($printer, array $modifiers): void
    {
        try {
            foreach ($modifiers as $mod) {
                $modProduct = $mod['product'] ?? [];
                $modName = $modProduct['name'] ?? ($mod['name'] ?? '');
                $modQty = (int) ($mod['quantity'] ?? 1);
                $modPrice = (float) ($modProduct['price'] ?? ($mod['price'] ?? 0));

                $nameNormalized = strtoupper($this->normalizeText($modName));

                // Prefijo para indicar modificador y reservar espacio en la columna de nombre
                $prefix = '  + ';
                $nameWidth = 28 - strlen($prefix); // Ajuste por prefijo

                $nameTruncated = strlen($nameNormalized) > $nameWidth
                    ? substr($nameNormalized, 0, $nameWidth)
                    : $nameNormalized;

                $nameCol = sprintf("%s%-" . $nameWidth . "s", $prefix, $nameTruncated);
                $qtyCol = sprintf("%4d", $modQty);
                $valueCol = $modPrice > 0
                    ? sprintf("%12s", $this->formatCurrency($modPrice * max(1, $modQty)))
                    : sprintf("%12s", '');

                $line = $nameCol . ' ' . $qtyCol . ' ' . $valueCol;
                $printer->text($line . "\n");

                // Imprimir el resto del nombre si fue truncado
                if (strlen($nameNormalized) > $nameWidth) {
                    $remaining = substr($nameNormalized, $nameWidth);
                    // Dividir el restante en trozos del mismo ancho de nombre
                    while ($remaining !== '') {
                        $chunk = substr($remaining, 0, $nameWidth);
                        $remaining = substr($remaining, strlen($chunk));
                        $printer->text(sprintf("%s%-" . $nameWidth . "s\n", '    ', $chunk));
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error imprimiendo modificadores', ['error' => $e->getMessage()]);
        }
    }

    private function triggerPrintAlert(Printer $printer, ?string $printerName = null, ?array $printSettings = null): void
    {
        try {
            $alertEnabled = $this->isPrintAlertEnabled($printSettings);
            if (!$alertEnabled) {
                return;
            }

            $connector = $printer->getPrintConnector();

            $times = $this->resolveAlertTimes($printSettings);
            $durationMs = $this->resolveAlertDurationMs($printSettings);
            $profile = $this->resolveAlertProfile($printerName, $printSettings);

            for ($i = 0; $i < $times; $i++) {
                // BEL: fallback más compatible entre diferentes firmwares ESC/POS.
                $connector->write("\x07");
                if ($durationMs > 0) {
                    usleep($durationMs * 1000);
                }
            }

            if ($profile === self::ALERT_PROFILE_SAT_Q22) {
                $this->sendSatQ22AlertSequence($connector, $times, $durationMs);
            } else {
                $this->sendEscPosExtendedAlertSequence($connector, $times, $durationMs);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo reproducir el beep de alerta', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isPrintAlertEnabled(?array $printSettings): bool
    {
        $settingValue = $printSettings['print_alert'] ?? $printSettings['beep_on_print'] ?? null;
        if ($settingValue !== null) {
            return filter_var($settingValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return filter_var(env('PRINT_ALERT_ENABLED', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function resolveAlertTimes(?array $printSettings): int
    {
        $times = (int) (
            $printSettings['print_alert_times']
            ?? $printSettings['beep_times']
            ?? env('PRINT_ALERT_TIMES', 2)
        );

        return max(1, min($times, 9));
    }

    private function resolveAlertDurationMs(?array $printSettings): int
    {
        $durationMs = (int) (
            $printSettings['print_alert_duration_ms']
            ?? $printSettings['beep_duration_ms']
            ?? env('PRINT_ALERT_DURATION_MS', 120)
        );

        return max(50, min($durationMs, 2000));
    }

    private function resolveAlertProfile(?string $printerName, ?array $printSettings): string
    {
        $profileFromSettings = $this->normalizeAlertProfile((string) (
            $printSettings['print_alert_profile']
            ?? $printSettings['beep_profile']
            ?? $printSettings['alert_profile']
            ?? ''
        ));
        if ($profileFromSettings !== null) {
            return $profileFromSettings;
        }

        $profileByBrand = $this->resolveAlertProfileByBrand($printSettings);
        if ($profileByBrand !== null) {
            return $profileByBrand;
        }

        $profileByPrinter = $this->resolveAlertProfileByPrinterName($printerName);
        if ($profileByPrinter !== null) {
            return $profileByPrinter;
        }

        $profileByContains = $this->resolveAlertProfileByContains($printerName);
        if ($profileByContains !== null) {
            return $profileByContains;
        }

        $profileByLegacyAutodetect = $this->resolveLegacyAutodetectProfile($printerName);
        if ($profileByLegacyAutodetect !== null) {
            return $profileByLegacyAutodetect;
        }

        $profileFromEnv = $this->normalizeAlertProfile((string) env('PRINT_ALERT_PROFILE', self::ALERT_PROFILE_DEFAULT));
        if ($profileFromEnv !== null) {
            return $profileFromEnv;
        }

        return self::ALERT_PROFILE_DEFAULT;
    }

    private function resolveAlertProfileByPrinterName(?string $printerName): ?string
    {
        $printerNameNormalized = strtolower(trim((string) $printerName));
        if ($printerNameNormalized === '') {
            return null;
        }

        $mapRaw = env('PRINT_ALERT_PROFILE_MAP', '');
        if (!is_string($mapRaw) || trim($mapRaw) === '') {
            return null;
        }

        $decoded = json_decode($mapRaw, true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $key => $value) {
            $alias = strtolower(trim((string) $key));
            $mappedProfile = $this->normalizeAlertProfile((string) $value);
            if ($alias === '' || $mappedProfile === null) {
                continue;
            }

            if ($alias === $printerNameNormalized) {
                return $mappedProfile;
            }
        }

        return null;
    }

    private function resolveAlertProfileByContains(?string $printerName): ?string
    {
        $printerNameNormalized = strtolower(trim((string) $printerName));
        if ($printerNameNormalized === '') {
            return null;
        }

        $mapRaw = env('PRINT_ALERT_PROFILE_CONTAINS_MAP', '');
        if (!is_string($mapRaw) || trim($mapRaw) === '') {
            return null;
        }

        $decoded = json_decode($mapRaw, true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $contains => $profile) {
            $containsNormalized = strtolower(trim((string) $contains));
            $mappedProfile = $this->normalizeAlertProfile((string) $profile);
            if ($containsNormalized === '' || $mappedProfile === null) {
                continue;
            }

            if (str_contains($printerNameNormalized, $containsNormalized)) {
                return $mappedProfile;
            }
        }

        return null;
    }

    private function resolveAlertProfileByBrand(?array $printSettings): ?string
    {
        $brand = strtolower(trim((string) (
            $printSettings['print_alert_brand']
            ?? $printSettings['printer_brand']
            ?? $printSettings['brand']
            ?? ''
        )));
        if ($brand === '') {
            return null;
        }

        $mapRaw = env('PRINT_ALERT_BRAND_PROFILE_MAP', '');
        if (!is_string($mapRaw) || trim($mapRaw) === '') {
            return $this->normalizeAlertProfile($brand);
        }

        $decoded = json_decode($mapRaw, true);
        if (!is_array($decoded)) {
            return $this->normalizeAlertProfile($brand);
        }

        foreach ($decoded as $brandKey => $profile) {
            $brandKeyNormalized = strtolower(trim((string) $brandKey));
            $mappedProfile = $this->normalizeAlertProfile((string) $profile);
            if ($brandKeyNormalized === '' || $mappedProfile === null) {
                continue;
            }

            if ($brand === $brandKeyNormalized) {
                return $mappedProfile;
            }
        }

        return $this->normalizeAlertProfile($brand);
    }

    private function resolveLegacyAutodetectProfile(?string $printerName): ?string
    {
        $printerNameNormalized = strtolower((string) $printerName);
        if (
            str_contains($printerNameNormalized, 'sat')
            || str_contains($printerNameNormalized, 'q22')
            || str_contains($printerNameNormalized, 'q22ue')
        ) {
            return self::ALERT_PROFILE_SAT_Q22;
        }

        return null;
    }

    private function normalizeAlertProfile(string $profile): ?string
    {
        $value = strtolower(trim($profile));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'sat_q22', 'sat', 'q22', 'q22ue', 'satq22', 'sat-q22', 'sat-q22ue' => self::ALERT_PROFILE_SAT_Q22,
            'generic', 'default', 'normal', 'star', '3nstar', 'epson', 'tm' => self::ALERT_PROFILE_DEFAULT,
            default => null,
        };
    }

    private function sendEscPosExtendedAlertSequence($connector, int $times, int $durationMs): void
    {
        try {
            // ESC B n t: soportado por varios modelos ESC/POS compatibles.
            $durationUnit100ms = (int) max(1, min(9, round($durationMs / 100)));
            $connector->write("\x1B\x42" . chr($times) . chr($durationUnit100ms));
        } catch (\Throwable $e) {
            // Ignorar si no está soportado por el firmware.
        }

        try {
            // ESC ( A fn=48: formato Epson para control de buzzer.
            // 1B 28 41 04 00 30 n c t
            $n = 51; // Patrón tonal seguro común.
            $c = max(1, min($times, 9));
            $t = max(10, min((int) round($durationMs / 100), 255));
            $connector->write("\x1B\x28\x41\x04\x00\x30" . chr($n) . chr($c) . chr($t));
        } catch (\Throwable $e) {
            // Ignorar si no está soportado por el firmware.
        }
    }

    private function sendSatQ22AlertSequence($connector, int $times, int $durationMs): void
    {
        try {
            // SAT Q22UE suele responder bien a esta variante ESC B.
            $durationUnit100ms = (int) max(1, min(9, round($durationMs / 100)));
            $connector->write("\x1B\x42" . chr($times) . chr($durationUnit100ms));
        } catch (\Throwable $e) {
            // Ignorar si no está soportado por el firmware.
        }

        $this->sendEscPosExtendedAlertSequence($connector, $times, $durationMs);
    }
}
