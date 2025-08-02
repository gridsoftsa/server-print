<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\PrinterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

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
                        $this->processOpenCashDrawer($value['printer']);
                        break;

                    case 'orderPrinter':
                        $this->processOrderPrint($controller, $value);
                        break;

                    case 'salePrinter':
                        $this->processSalePrint($value);
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
     * Procesar apertura de caja
     */
    private function processOpenCashDrawer($name)
    {
        $connector = new WindowsPrintConnector($name);
        $printer = new Printer($connector);
        $printer->pulse();
        $printer->close();
        return response()->json(['message' => 'Caja abierta'], 200);
    }

    /**
     * Procesar impresión de orden tradicional
     */
    private function processOrderPrint($controller, $value)
    {
        // Verificar si viene con data_json (nuevo sistema) o image (tradicional)
        if (!empty($value['data_json'])) {
            // 🚀 MODO ESC/POS OPTIMIZADO: usar comandos nativos
            $data = [
                'printerName' => $value['printer'],
                'orderData' => $value['data_json'],
                'openCash' => $value['open_cash'] ?? false,
                'useJsonMode' => true // Activar modo ESC/POS optimizado
            ];
        } else {
            // 🐌 MODO TRADICIONAL: usar imagen (lento)
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
     * 🚀 PROCESAR IMPRESIÓN DE VENTA - ULTRA RÁPIDO
     *
     * El logo viene como URL desde this.company.logo
     * Se descarga una sola vez y se mantiene en caché permanente
     * La imagen de factura cambia cada vez y se procesa temporalmente
     */
    private function processSalePrint($value)
    {
        // 🚀 OPTIMIZACIÓN MÁXIMA: Procesar directamente sin pasar por métodos intermedios
        $this->printSaleUltraFast($value['printer'], $value['image'], $value['logo'] ?? null, $value['open_cash'] ?? false, $value['logo_base64'] ?? null);
    }

    /**
     * 🚀 MÉTODO ULTRA RÁPIDO: Imprimir venta con imagen - OPTIMIZACIÓN MÁXIMA
     */
    private function printSaleUltraFast($printerName, $base64Image, $logo, $openCash = false, $logoBase64 = null)
    {
        try {
            // 🚀 OPTIMIZACIÓN 1: Configurar memoria y timeouts para máxima velocidad
            ini_set('memory_limit', '1024M');

            // 🚀 OPTIMIZACIÓN 2: Validación ultra rápida
            if (empty($base64Image)) {
                return;
            }

            // 🚀 OPTIMIZACIÓN 3: Decodificar base64 directamente sin regex lento
            $imageData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $base64Image));

            // 🚀 OPTIMIZACIÓN 4: Usar directorio temporal del sistema (más rápido)
            $tempPath = sys_get_temp_dir() . '/sale_' . uniqid() . '.png';
            file_put_contents($tempPath, $imageData);

            // 🚀 OPTIMIZACIÓN 5: PRIORIZAR logo_base64 SOBRE logo URL
            $tempPathLogo = null;
            if ($logoBase64 && !empty($logoBase64)) {
                // 🚀 PRIORIDAD ALTA: Usar logo_base64 directamente
                $logoData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $logoBase64));
                $tempPathLogo = sys_get_temp_dir() . '/logo_' . uniqid() . '.png';
                file_put_contents($tempPathLogo, $logoData);
            } elseif ($logo && !empty($logo)) {
                // 🚀 FALLBACK: Usar logo URL si no hay logo_base64
                $logoHash = md5($logo);
                $cacheDir = storage_path('app/public/logo_cache');
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                $tempPathLogo = $cacheDir . '/company_logo_' . $logoHash . '.png';

                if (!file_exists($tempPathLogo)) {
                    $logoData = file_get_contents($logo);
                    if ($logoData !== false) {
                        file_put_contents($tempPathLogo, $logoData);
                    } else {
                        $tempPathLogo = null;
                    }
                }
            }

            // 🚀 OPTIMIZACIÓN 6: Conexión directa a impresora sin validaciones extra
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // 🚀 OPTIMIZACIÓN 7: Imprimir logo si existe
            if ($tempPathLogo && file_exists($tempPathLogo)) {
                $imgLogo = EscposImage::load($tempPathLogo);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);
            }

            // 🚀 OPTIMIZACIÓN 8: Imprimir imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(1);
            $printer->cut();

            // 🚀 OPTIMIZACIÓN 9: Abrir caja si es necesario
            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            // 🚀 OPTIMIZACIÓN 10: Limpieza ultra rápida
            @unlink($tempPath); // Eliminar imagen de factura temporal

            // Limpiar logo temporal si es base64 (no caché)
            if ($tempPathLogo && strpos($tempPathLogo, 'logo_') !== false) {
                @unlink($tempPathLogo); // Solo archivos temporales base64
            }
            // Los archivos de caché (logo_cache/) se mantienen para reutilización
        } catch (\Exception $e) {
            // 🚀 OPTIMIZACIÓN: Limpieza en caso de error
            @unlink($tempPath ?? '');

            // Limpiar logo temporal si es base64 (no caché)
            if (isset($tempPathLogo) && $tempPathLogo && strpos($tempPathLogo, 'logo_') !== false) {
                @unlink($tempPathLogo); // Solo archivos temporales base64
            }
            // Los archivos de caché (logo_cache/) se mantienen para reutilización
        }
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
