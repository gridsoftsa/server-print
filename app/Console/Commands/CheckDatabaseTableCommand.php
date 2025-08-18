<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\PrinterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckDatabaseTableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:check-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica una tabla en la base de datos cada 1 segundo';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $api = env('APP_ENV') == 'local' ? 'api' : 'api-demo';
        $api_url_pos = env('API_URL_POS');
        $controller = app(PrinterController::class);
        $url = "https://$api.gridpos.co/print-queue";

        $response = Http::withHeaders([
            'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
            'X-Client-Slug' => $api_url_pos,
        ])->withoutVerifying()->get($url);
        $data_resp = $response->json();

        // Si response es un array vacío, terminar inmediatamente
        if (empty($data_resp)) {
            return 0;
        }

        // 🔍 VALIDAR ESTRUCTURA DE DATOS
        if (!is_array($data_resp)) {
            return 0;
        }

        foreach ($data_resp as $key => $value) {
            try {
                // 🔍 VALIDAR QUE $value SEA UN ARRAY Y TENGA LA CLAVE 'action'
                if (!is_array($value)) {
                    continue;
                }

                if (!isset($value['action'])) {
                    continue;
                }
                switch ($value['action']) {
                    case 'openCashDrawer':
                        $controller->openCash($value['printer']);
                        $this->deletePrintQueue($url, $value['id'], $api_url_pos);
                        break;

                    case 'orderPrinter':
                        $this->processOrderPrint($controller, $value);
                        break;

                    case 'salePrinter':
                        $this->processSalePrint($controller, $value);
                        break;

                    default:
                        break;
                }

                // Después de procesar exitosamente, eliminar el registro
                $this->deletePrintQueue($url, $value['id'], $api_url_pos);
            } catch (\Exception $e) {
                // 🔧 INTENTAR RECUPERACIÓN: Si el error es por estructura de datos
                if (strpos($e->getMessage(), 'Cannot access offset') !== false) {
                    continue;
                }

                // Si hay ID válido, intentar eliminar el registro problemático
                if (isset($value['id']) && is_numeric($value['id'])) {
                    $this->deletePrintQueue($url, $value['id'], $api_url_pos);
                }
            }
        }

        return 0;
    }
    /**
     * Procesar impresión de orden tradicional
     */
    private function processOrderPrint($controller, $value)
    {
        // Verificar si viene con data_json (nuevo sistema)
        $data = [
            'printerName' => $value['printer'],
            'orderData' => $value['data_json'],
            'openCash' => $value['open_cash'] ?? false,
            'useJsonMode' => true // Activar modo ESC/POS optimizado
        ];

        $request = Request::create('/', 'GET', $data);
        $controller->printOrder($request);
    }

    /**
     * 🚀 PROCESAR IMPRESIÓN DE VENTA - ULTRA RÁPIDO
     *
     * El logo viene como URL desde this.company.logo
     * Se descarga una sola vez y se mantiene en caché permanente
     * La imagen de factura cambia cada vez y se procesa temporalmente
     */
    private function processSalePrint($controller, $value)
    {
        // 🚀 OPTIMIZACIÓN MÁXIMA: Procesar directamente sin pasar por métodos intermedios
        $controller->printSale($value['printer'], $value['image'], $value['logo'] ?? null, $value['open_cash'] ?? false, $value['logo_base64'] ?? null);
    }
    /**
     * Eliminar registro de la cola de impresión
     */
    private function deletePrintQueue($baseUrl, $id, $apiUrlPos)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
                'X-Client-Slug' => $apiUrlPos,
            ])->withoutVerifying()->get($baseUrl . '/' . $id);
        } catch (\Exception $e) {
        }
    }
}
