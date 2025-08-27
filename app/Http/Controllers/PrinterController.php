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
            if (empty($base64Image) || empty($logoBase64)) {
                return;
            }

            // 🚀 OPTIMIZACIÓN 3: Decodificar base64 directamente sin regex lento
            $imageData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $base64Image));

            // 🚀 OPTIMIZACIÓN 4: Usar directorio temporal del sistema (más rápido)
            $tempPath = sys_get_temp_dir() . '/sale_' . uniqid() . '.png';
            file_put_contents($tempPath, $imageData);

            // 🚀 OPTIMIZACIÓN 5: PRIORIZAR logo_base64 SOBRE logo URL
            $tempPathLogo = null;
            if ($logoBase64 && !empty($logoBase64)) {
                // 🚀 PRIORIDAD ALTA: Usar logo_base64 directamente
                $logoData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $logoBase64));
                $tempPathLogo = sys_get_temp_dir() . '/logo_' . uniqid() . '.png';
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
            // Los archivos de caché (logo_cache/) se mantienen para reutilización
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
