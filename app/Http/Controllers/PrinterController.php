<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PrinterService;

class PrinterController extends Controller
{
    private PrinterService $printerService;

    public function __construct(PrinterService $printerService)
    {
        $this->printerService = $printerService;
    }

    public function openCash($name = 'POS-80')
    {
        try {
            $this->printerService->openCash($name);
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
            ini_set('memory_limit', '1024M');
            $result = $this->printerService->printOrder($printerName, $orderData, $openCash);
            return response()->json($result, 200);
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
            ini_set('memory_limit', '1024M');
            $this->printerService->printSale(
                $request->printerName,
                $request->base64Image,
                (bool) $request->openCash,
                $request->logoBase64,
                $request->logo
            );
            return response()->json(['message' => 'Venta enviada a impresión'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al imprimir la venta', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🧾 Imprimir venta completa con ESC/POS (basado en SaleFormatter.kt)
     * Maneja el formato completo de factura como TicketPrint.vue
     */
    public function printSaleEscPos(Request $request)
    {
        try {
            ini_set('memory_limit', '1024M');
            $printerName = $request->input('printerName', 'POS-80');
            $openCash = (bool) $request->input('openCash', false);
            $saleData = $request->input('dataJson', $request->all());
            $company = $request->input('company', null);
            $logoBase64 = $request->input('logoBase64', null);

            $this->printerService->printSaleEscPos($printerName, $saleData, $openCash, $company, $logoBase64);

            return response()->json([
                'message' => 'Venta impresa correctamente con ESC/POS',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al imprimir venta con ESC/POS',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}
