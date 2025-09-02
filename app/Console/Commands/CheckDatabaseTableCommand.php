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
        $clientSlug = env('API_URL_POS');
        $controller = app(PrinterController::class);
        $url = "https://$api.gridpos.co/print-queue";

        Log::info('🔍 URL: ' . $url);
        $response = Http::withHeaders([
            'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
            'X-Client-Slug' => $clientSlug,
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
                $action = $value['action'];
                Log::info('value: ' . json_encode($value));
                $handlers = [
                    'openCashDrawer' => function () use ($controller, $value) {
                        $controller->openCash($value['printer']);
                    },
                    'orderPrinter' => function () use ($controller, $value) {
                        $this->processOrderPrint($controller, $value);
                    },
                    'salePrinter' => function () use ($controller, $value) {
                        $this->processSalePrint($controller, $value);
                    },
                ];

                if (isset($handlers[$action])) {
                    $handlers[$action]();
                }

                // Después de procesar exitosamente, eliminar el registro
                $this->deletePrintQueue($url, $value['id'], $clientSlug);
            } catch (\Exception $e) {
                // 🔧 INTENTAR RECUPERACIÓN: Si el error es por estructura de datos
                if (strpos($e->getMessage(), 'Cannot access offset') !== false) {
                    continue;
                }

                // Si hay ID válido, intentar eliminar el registro problemático
                if (isset($value['id']) && is_numeric($value['id'])) {
                    $this->deletePrintQueue($url, $value['id'], $clientSlug);
                }
            }
        }

        return 0;
    }

    private function processSalePrint($controller, $value)
    {
        try {
            $data = [
                'printerName' => $value['printer'],
                'base64Image' => $value['image'] ?? null,
                'logoBase64' => $value['logo_base64'] ?? null,
                'logo' => $value['logo'] ?? null,
                'openCash' => $value['open_cash'] ?? false,
                'useImage' => $value['print_settings']['use_image'] ?? false,
                'dataJson' => $value['data_json'] ?? null,
            ];
            $request = Request::create('/', 'GET', $data);
            if ($value['print_settings']['use_image']) {
                $controller->printSale($request);
            }
            if (!$value['print_settings']['use_image']) {
                $controller->printSaleEscPos($request);
            }
        } catch (\Exception $e) {
            Log::error('Error procesando impresión de venta: ' . $e->getMessage(), [
                'printer' => $value['printer'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Procesar impresión de orden tradicional
     */
    private function processOrderPrint($controller, $value)
    {
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
