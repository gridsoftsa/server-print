<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;

class PrinterController extends Controller
{
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

    public function printOrder(Request $request)
    {
        ini_set('memory_limit', '256M');

        Log::info('printOrder');
        $printerName = $request->printerName; // Nombre de la impresora
        $openCash = $request->openCash ?? false; // Si open_cash no está presente, por defecto es false
        $base64Image = $request->input('image'); // Captura la imagen en base64

        // Decodificar el string base64 para obtener los datos binarios de la imagen
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Guardar temporalmente la imagen decodificada
        $tempPath = storage_path('app/public/temp_image.png');
        file_put_contents($tempPath, $imageData);

        try {
            // Crear el conector e instancia de la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar la imagen desde el archivo temporal
            $img = EscposImage::load($tempPath);

            // Imprimir la imagen centrada
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(2); // Añade 2 líneas en blanco al final para espacio adicional

            // Corta el papel
            $printer->cut();

            // Abrir la caja si el parámetro ⁠ open_cash ⁠ es true
            if ($openCash) {
                $printer->pulse();
            }

            // Cerrar la impresora
            $printer->close();

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la orden: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        }

        /**
         * Esto para revisar si lo otro no sirve
         */
        /* try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Orden de venta\n");
            $printer->text("Fecha: " . date('Y-m-d H:i:s') . "\n");
            $printer->text("Orden: " . $order . "\n");
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            return response()->json(['message' => 'Orden impresa'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        } */
    }

    public function printSale(Request $request)
    {
        ini_set('memory_limit', '256M');
        ini_set('gd.jpeg_ignore_warning', 1); // Ignora advertencias de imágenes problemáticas

        Log::info('printSale');
        $printerName = $request->printerName; // Nombre de la impresora
        $openCash = $request->openCash ?? false; // Si open_cash no está presente, por defecto es false
        $base64Image = $request->input('image'); // Captura la imagen en base64

        // Decodificar el string base64 para obtener los datos binarios de la imagen
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Guardar temporalmente la imagen decodificada
        $tempPath = storage_path('app/public/temp_image.png');
        file_put_contents($tempPath, $imageData);

        try {
            // Crear el conector e instancia de la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // imprimir logo centrado
            $url = $request->url_logo;
            $tempLogoPath = storage_path('app/public/temp_logo.png'); // Ruta temporal
            $imageContent = file_get_contents($url);

            // Convertir a formato PNG si es necesario
            $imageResource = imagecreatefromstring($imageContent);

            // Solución para el perfil de color incorrecto
            if (!$imageResource) {
                throw new Exception("Error al procesar la imagen");
            }

            // Limpia perfiles ICC y reconstruye la imagen
            ob_start();
            imagepng($imageResource);
            $imageDataClean = ob_get_clean();

            $imageResource = imagecreatefromstring($imageDataClean);

            $originalWidth = imagesx($imageResource);
            $originalHeight = imagesy($imageResource);

            $newWidth = 350; // Ancho fijo
            $newHeight = ($originalHeight / $originalWidth) * $newWidth; // Alto proporcional

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled(
                $resizedImage,
                $imageResource,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            imagepng($resizedImage, $tempLogoPath); // Guardar como PNG
            imagedestroy($imageResource); // Liberar memoria
            imagedestroy($resizedImage); // Liberar memoria

            $img = EscposImage::load($tempLogoPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);

            // Cargar la imagen desde el archivo temporal
            $img = EscposImage::load($tempPath);

            // Imprimir la imagen centrada
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(2); // Añade 2 líneas en blanco al final para espacio adicional

            // Corta el papel
            $printer->cut();

            // Abrir la caja si el parámetro ⁠ open_cash ⁠ es true
            if ($openCash) {
                $printer->pulse();
            }

            // Cerrar la impresora
            $printer->close();

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la orden: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        } finally {
            if (isset($printer)) {
                $printer->close(); // Asegúrate de cerrar siempre la impresora
            }
        }

        /**
         * Esto para revisar si lo otro no sirve
         */
        /* try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Orden de venta\n");
            $printer->text("Fecha: " . date('Y-m-d H:i:s') . "\n");
            $printer->text("Orden: " . $order . "\n");
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            return response()->json(['message' => 'Orden impresa'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        } */
    }
}
