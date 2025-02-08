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
        ini_set('memory_limit', '1024M');

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

            // Enviar comando para el pitido
            //$printer->textRaw("\x1B(B"); // Opción 1

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
    }

    public function printSale(Request $request)
    {
        ini_set('memory_limit', '1024M'); // Aumentar memoria a 1GB

        Log::info('printSale');
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $base64Image = $request->input('image');
        $logoBase64 = $request->input('logoBase64');

        // Decodificar Base64 y comprimir imagen
        function compressImage($source, $destination, $quality)
        {
            $image = imagecreatefromstring($source);
            imagejpeg($image, $destination, $quality);
            imagedestroy($image);
        }

        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
        $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoBase64));

        // Guardar imágenes temporales
        $tempPath = storage_path('app/public/temp_image.jpg');
        compressImage($imageData, $tempPath, 60);

        $tempPathLogo = storage_path('app/public/temp_logo.jpg');
        compressImage($logoData, $tempPathLogo, 60);

        try {
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar y mostrar logo
            $imgLogo = EscposImage::load($tempPathLogo);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($imgLogo);
            $printer->feed(1);

            // Cargar y mostrar imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(2);

            $printer->cut();

            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            // Eliminar archivos temporales
            unlink($tempPath);
            unlink($tempPathLogo);

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la factura: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la factura', 'error' => $e->getMessage()], 500);
        }
    }
}
