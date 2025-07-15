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
    protected $description = 'Verifica una tabla en la base de datos cada 5 segundos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $api_url_pos = env('API_URL_POS');
        $controller = app(PrinterController::class);
        $url = "https://api-demo.gridpos.co/print-queue";

        $response = Http::withHeaders([
            'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
            'X-Client-Slug' => $api_url_pos,
        ])->withoutVerifying()->get($url);

        Log::info('Requesting API URL: ' . $url, [
            'headers' => [
                'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
                'X-Client-Slug' => $api_url_pos,
            ]
        ]);

        $data_resp = $response->json();

        // Si response es un array vacío, terminar inmediatamente
        if (empty($data_resp)) {
            return 0;
        }

        // 🔍 VALIDAR ESTRUCTURA DE DATOS
        if (!is_array($data_resp)) {
            return 0;
        }

        Log::info('Procesando impresiones y apertura de caja');
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
                        $this->processOpenCashDrawer($controller, $value);
                        break;

                    case 'orderPrinter':
                        $this->processOrderPrint($controller, $value);
                        break;

                    case 'salePrinter':
                        $this->processSalePrint($controller, $value);
                        break;

                    default:
                        Log::warning('Acción no reconocida: ' . $value['action']);
                        break;
                }

                // Después de procesar exitosamente, eliminar el registro
                $this->deletePrintQueue($url, $value['id'], $api_url_pos);
            } catch (\Exception $e) {
                Log::error('Error procesando elemento de la cola', [
                    'element_id' => $value['id'] ?? 'N/A',
                    'element_action' => $value['action'] ?? 'N/A',
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                    'element_data' => $value
                ]);

                // 🔧 INTENTAR RECUPERACIÓN: Si el error es por estructura de datos
                if (strpos($e->getMessage(), 'Cannot access offset') !== false) {
                    Log::warning('Elemento con estructura incorrecta detectado, saltando...', [
                        'element_key' => $key,
                        'element_type' => gettype($value),
                        'element_value' => $value
                    ]);
                    continue;
                }

                // Si hay ID válido, intentar eliminar el registro problemático
                if (isset($value['id']) && is_numeric($value['id'])) {
                    Log::info('Eliminando registro problemático de la cola', ['id' => $value['id']]);
                    $this->deletePrintQueue($url, $value['id'], $api_url_pos);
                }
            }
        }

        return 0;
    }

    /**
     * Procesar apertura de caja
     */
    private function processOpenCashDrawer($controller, $value)
    {
        Log::info('Abriendo caja en impresora: ' . $value['printer']);
        $controller->openCash($value['printer']);
    }

    /**
     * Procesar impresión de orden tradicional
     */
    private function processOrderPrint($controller, $value)
    {
        Log::info('Imprimiendo orden en impresora: ' . $value['printer']);

        // Verificar si viene con data_json (nuevo sistema) o image (tradicional)
        if (!empty($value['data_json'])) {
            // 🚀 MODO ESC/POS OPTIMIZADO: usar comandos nativos
            Log::info('🚀 Procesando orden con datos JSON - Modo ESC/POS OPTIMIZADO (ultra rápido)');

            // 🔍 DEBUG: Log completo de los datos de impresión
            Log::info('🔍 DEBUG datos completos de print_settings', [
                'printer' => $value['printer'],
                'complete_data_json' => $value['data_json'],
                'print_settings' => $value['data_json']['print_settings'] ?? 'NO_PRINT_SETTINGS',
                'paper_width_raw' => $value['data_json']['print_settings']['paper_width'] ?? 'NO_PAPER_WIDTH',
                'paper_width_type' => gettype($value['data_json']['print_settings']['paper_width'] ?? null)
            ]);

            Log::info('🚀 Ancho de papel: ' . ($value['data_json']['print_settings']['paper_width'] ?? 'NO_SET'));
            $data = [
                'printerName' => $value['printer'],
                'orderData' => $value['data_json'],
                'openCash' => $value['open_cash'] ?? false,
                'useJsonMode' => true // Activar modo ESC/POS optimizado
            ];
        } else {
            // 🐌 MODO TRADICIONAL: usar imagen (lento)
            Log::info('🐌 Procesando orden con imagen - Modo tradicional (lento)');
            $data = [
                'printerName' => $value['printer'],
                'image' => $value['image'],
                'openCash' => $value['open_cash'] ?? false,
                'useJsonMode' => false // Mantener modo imagen tradicional
            ];
        }

        $request = Request::create('/', 'GET', $data);
        $controller->printOrder($request);
    }

    /**
     * Procesar impresión de venta
     */
    private function processSalePrint($controller, $value)
    {
        Log::info('Imprimiendo venta en impresora: ' . $value['printer']);

        // Verificar si viene con data_json (nuevo sistema ESC/POS) o image (tradicional)
        if (!empty($value['data_json'])) {
            // 🚀 MODO ESC/POS OPTIMIZADO: usar comandos nativos para ventas
            Log::info('🚀 Procesando venta con datos JSON - Modo ESC/POS OPTIMIZADO (ultra rápido)');
            $data = [
                'printerName' => $value['printer'],
                'saleData' => $value['data_json'], // Datos de venta estructurados
                'openCash' => $value['open_cash'] ?? false,
                'useJsonMode' => true // Activar modo ESC/POS optimizado
            ];
        } else {
            // 🐌 MODO TRADICIONAL: usar imagen (lento)
            Log::info('🐌 Procesando venta con imagen - Modo tradicional (lento)');
            Log::info($value);
            $data = [
                'printerName' => $value['printer'],
                'image' => $value['image'],
                'logoBase64' => $value['logo'] ?? null,
                'openCash' => $value['open_cash'] ?? false,
                'useJsonMode' => false // Mantener modo imagen tradicional
            ];
        }

        $request = Request::create('/', 'GET', $data);
        $controller->printSale($request);
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

            Log::info('Registro eliminado de la cola: ' . $id);
        } catch (\Exception $e) {
            Log::error('Error eliminando registro de la cola: ' . $e->getMessage());
        }
    }
}
