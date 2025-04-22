<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\ImagickEscposImage;

class PrinterController extends Controller
{
    // Ancho máximo de impresión para ajustar imágenes automáticamente (en píxeles)
    private $maxPrintWidth = 576; // Para impresoras estándar de 80mm
    private $compressionQuality = 35; // Calidad de compresión JPEG (más bajo = más compresión)

    public function openCash($name = 'POS-80')
    {
        Log::info('openDrawer');
        $connector = new WindowsPrintConnector($name);
        $printer = new Printer($connector);

        try {
            // Comando ESC/POS para abrir la caja registradora
            $printer->pulse();
            $printer->close();

            return response()->json(['message' => 'Caja abierta'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al abrir la caja', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Procesa una imagen base64 para optimizarla para impresión
     */
    private function processBase64Image($base64Image)
    {
        // Limpiar datos de cabecera
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Crear imagen desde los datos binarios
        $image = \imagecreatefromstring($imageData);

        // Obtener dimensiones originales
        $origWidth = \imagesx($image);
        $origHeight = \imagesy($image);

        // Si la imagen es más ancha que el máximo permitido, redimensionarla
        if ($origWidth > $this->maxPrintWidth) {
            $newWidth = $this->maxPrintWidth;
            $newHeight = ($origHeight / $origWidth) * $newWidth;

            $tempImage = \imagecreatetruecolor($newWidth, $newHeight);

            // Convertir a escala de grises para impresoras térmicas
            \imagefilter($image, IMG_FILTER_GRAYSCALE);

            // Redimensionar con mejor calidad
            \imagecopyresampled($tempImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            \imagedestroy($image);
            $image = $tempImage;
        }

        // Optimizar contraste para impresión térmica
        \imagefilter($image, IMG_FILTER_CONTRAST, -10);

        // Convertir a memoria
        ob_start();
        \imagejpeg($image, null, $this->compressionQuality);
        $imageData = ob_get_contents();
        ob_end_clean();

        \imagedestroy($image);

        // Guarda temporalmente la imagen optimizada
        $tempPath = storage_path('app/public/temp_image_opt.jpg');
        file_put_contents($tempPath, $imageData);

        return $tempPath;
    }

    public function printOrder(Request $request)
    {
        ini_set('memory_limit', '1024M');

        Log::info('printOrder');
        $printerName = $request->printerName; // Nombre de la impresora
        $openCash = $request->openCash ?? false; // Si open_cash no está presente, por defecto es false
        $base64Image = $request->input('image'); // Captura la imagen en base64

        if (empty($base64Image)) {
            return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
        }

        try {
            // Procesar imagen para optimizarla
            $tempImagePath = $this->processBase64Image($base64Image);

            // Crear imagen temporal en memoria
            $tempImage = EscposImage::load($tempImagePath);

            // Crear el conector e instancia de la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Imprimir la imagen centrada
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImageColumnFormat($tempImage);
            $printer->feed(2);
            $printer->cut();

            // Corta el papel
            // $printer->textRaw("\x1B(B"); // Opción 1

            // Abrir la caja si el parámetro ⁠ open_cash ⁠ es true
            if ($openCash) {
                $printer->pulse();
            }

            // Cerrar la impresora
            $printer->close();

            // Eliminar archivo temporal
            @unlink($tempImagePath);

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la orden: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        }
    }

    public function printSale(Request $request)
    {
        ini_set('memory_limit', '1024M'); // Aumentar memoria a 1GB

        Log::info('printSale');
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $base64Image = $request->input('image');
        $logoBase64 = $request->input('logoBase64');

        if (empty($base64Image)) {
            return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
        }

        try {
            // Procesar imágenes para optimizarlas
            $tempImagePath = $this->processBase64Image($base64Image);
            $tempLogoPath = $logoBase64 ? $this->processBase64Image($logoBase64) : null;

            // Conectar e imprimir
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Imprimir logo si existe
            if ($tempLogoPath) {
                $logoImage = EscposImage::load($tempLogoPath);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImageColumnFormat($logoImage);
                $printer->feed(1);
            }

            // Imprimir imagen principal
            $mainImage = EscposImage::load($tempImagePath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImageColumnFormat($mainImage);
            $printer->feed(1);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            // Eliminar archivos temporales
            @unlink($tempImagePath);
            if ($tempLogoPath) {
                @unlink($tempLogoPath);
            }

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la factura: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la factura', 'error' => $e->getMessage()], 500);
        }
    }
}
