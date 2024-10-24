<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer as OpenPrinter;
use Illuminate\Support\Facades\Log;

class Printer extends Controller
{
    public function openCash($name = 'POS-80')
    {
        Log::info('openDrawer');
        $connector = new WindowsPrintConnector($name);
        $printer = new OpenPrinter($connector);

        try {
            // Comando ESC/POS para abrir la caja registradora
            $printer->pulse();
            $printer->close();

            return response()->json(['message' => 'Caja abierta'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al abrir la caja', 'error' => $e->getMessage()], 500);
        }
    }
}
