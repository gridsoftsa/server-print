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
        ini_set('max_execution_time', 60);

        Log::info('Iniciando impresión de orden en: ' . ($request->printerName ?? 'impresora no especificada'));
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $base64Image = $request->input('image');

        if (empty($base64Image)) {
            Log::error('Error: Imagen no proporcionada para printOrder');
            return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
        }

        // Decodificar el string base64 para obtener los datos binarios de la imagen
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Guardar temporalmente la imagen decodificada
        $tempPath = storage_path('app/public/temp_image.png');
        file_put_contents($tempPath, $imageData);

        try {
            // Crear el conector e instancia de la impresora con buffer optimizado
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar la imagen desde el archivo temporal
            $img = EscposImage::load($tempPath);
            Log::info('Imagen cargada correctamente');

            // Imprimir con densidad reducida para mayor velocidad
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImageColumnFormat($img, Printer::IMG_DOUBLE_WIDTH);
            $printer->feed(2);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
                Log::info('Caja abierta como parte del proceso de impresión');
            }

            $printer->close();

            // Eliminar archivo temporal inmediatamente
            @unlink($tempPath);

            Log::info('Orden impresa correctamente en: ' . $printerName);
            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la orden: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la orden', 'error' => $e->getMessage()], 500);
        }
    }

    public function printSale(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 60);

        Log::info('Iniciando impresión de venta en: ' . ($request->printerName ?? 'impresora no especificada'));
        $printerName = $request->printerName;
        $openCash = $request->openCash ?? false;
        $base64Image = $request->input('image');
        $logoBase64 = $request->input('logoBase64');

        if (empty($base64Image)) {
            Log::error('Error: Imagen no proporcionada para printSale');
            return response()->json(['message' => 'Error: Imagen no proporcionada'], 400);
        }

        // Decodificar el string base64
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Guardar imagen temporal
        $tempPath = storage_path('app/public/temp_image.png');
        file_put_contents($tempPath, $imageData);

        // Procesar logo si existe
        $tempPathLogo = null;
        if ($logoBase64) {
            $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoBase64));
            $tempPathLogo = storage_path('app/public/temp_logo.png');
            file_put_contents($tempPathLogo, $logoData);
            Log::info('Logo procesado correctamente');
        }

        try {
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar y mostrar logo si está presente
            if ($tempPathLogo) {
                $imgLogo = EscposImage::load($tempPathLogo);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImageColumnFormat($imgLogo, Printer::IMG_DOUBLE_WIDTH);
                $printer->feed(1);
            }

            // Cargar y mostrar imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImageColumnFormat($img, Printer::IMG_DOUBLE_WIDTH);
            $printer->feed(2);
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

            Log::info('Venta impresa correctamente en: ' . $printerName);
            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al imprimir la venta: ' . $e->getMessage());
            return response()->json(['message' => 'Error al imprimir la factura', 'error' => $e->getMessage()], 500);
        }
    }
}
