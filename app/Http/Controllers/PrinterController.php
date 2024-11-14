<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Log;

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
        Log::info('printOrder');
        $printerName = $request->printerName; // Nombre de la impresora
        $htmlContent = $request->htmlContent; // Contenido HTML a imprimir
        $openCash = $request->openCash ?? false; // Si open_cash no está presente, por defecto es false

        try {
            // Crear el conector e instancia de la impresora
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Convertir el HTML en texto (Opcional)
            // Aquí puedes usar una librería para convertir el HTML en texto o formatearlo
            // según el tipo de impresora. Como las impresoras térmicas suelen usar solo
            // texto plano, puede que necesites extraer el texto del HTML.

            // Imprimir el contenido HTML
            $printer->text($htmlContent . "\n");
            $printer->cut();

            // Abrir la caja si el parámetro `open_cash` es true
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
}
