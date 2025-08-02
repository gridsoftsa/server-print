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
            ini_set('memory_limit', '1024M'); // Reducir memoria para mayor velocidad

            // 🚀 OPTIMIZACIÓN 2: Validación ultra rápida
            if (empty($base64Image)) {
                return;
            }

            // 🚀 OPTIMIZACIÓN 3: Decodificar base64 directamente sin regex lento
            $imageData = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $base64Image));

            // 🚀 OPTIMIZACIÓN 3: Decodificar base64 directamente sin regex lento
            $logo = base64_decode(str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', 'data:image/jpg;base64,'], '', $logoBase64));


            // 🚀 OPTIMIZACIÓN 4: Usar directorio temporal del sistema (más rápido)
            $tempPath = sys_get_temp_dir() . '/sale_' . uniqid() . '.png';
            file_put_contents($tempPath, $imageData);

            $tempPathLogoBase64 = sys_get_temp_dir() . '/sale_' . uniqid() . '.png';
            file_put_contents($tempPathLogoBase64, $logo);

            // 🚀 OPTIMIZACIÓN 5: CACHÉ PERMANENTE DEL LOGO DE EMPRESA (URL desde this.company.logo)
            $tempPathLogo = null;
            if ($logoBase64 && !empty($logoBase64)) {
                // 🚀 ULTRA RÁPIDO: Logo siempre viene como URL desde this.company.logo
                $logoHash = md5($logoBase64);
                $cacheDir = storage_path('app/public/logo_cache');
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                $tempPathLogo = $cacheDir . '/company_logo_' . $logoHash . '.png';

                // 🚀 ULTRA RÁPIDO: Si ya existe en caché, usar inmediatamente
                if (!file_exists($tempPathLogo)) {
                    $logoData = file_get_contents($logoBase64);
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

            // 🚀 OPTIMIZACIÓN 10: Limpieza ultra rápida (SOLO imagen de factura, NO logo de empresa)
            @unlink($tempPath); // Solo eliminar imagen de factura temporal

            // 🚀 NO ELIMINAR LOGO DE EMPRESA: Se mantiene en caché permanente para reutilización
            // El logo de empresa siempre es el mismo, no necesita limpieza
        } catch (\Exception $e) {
            // 🚀 OPTIMIZACIÓN: Limpieza en caso de error (SOLO imagen de factura)
            @unlink($tempPath ?? '');

            // 🚀 NO ELIMINAR LOGO DE EMPRESA: Se mantiene en caché permanente
            // El logo de empresa siempre es el mismo, no se elimina en caso de error
        }
    }

    public function printSale($data)
    {
        ini_set('memory_limit', '1024M');

        $printerName = $data['printerName'];
        $openCash = $data['openCash'] ?? false;
        $base64Image = $data['image'];
        $logoBase64 = $data['logoBase64'];

        if (empty($base64Image)) {
            return response()->json(['message' => 'Imagen no proporcionada'], 400);
        }

        return $this->printSaleWithImage($printerName, $base64Image, $logoBase64, $openCash);
    }

    /**
     * 🐌 MÉTODO TRADICIONAL: Imprimir venta usando imagen (lento - solo compatibilidad)
     */
    private function printSaleWithImage($printerName, $base64Image, $logoBase64, $openCash = false)
    {
        try {
            // Decodificar el string base64
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

            // Guardar imagen temporal
            $tempPath = storage_path('app/public/temp_image_' . uniqid() . '.png');
            file_put_contents($tempPath, $imageData);

            // 🚀 PROCESAMIENTO OPTIMIZADO DE LOGO para método tradicional
            $tempPathLogo = null;

            if ($logoBase64) {
                // Verificar si es una URL o base64
                if (filter_var($logoBase64, FILTER_VALIDATE_URL)) {
                    // 🚀 MODO OPTIMIZADO: Es una URL, descargarla
                    $tempPathLogo = $this->downloadLogoFromUrl($logoBase64);

                    if (!$tempPathLogo || !file_exists($tempPathLogo)) {
                        $tempPathLogo = null;
                    }
                } else {
                    // 🐌 MODO TRADICIONAL: Es base64, procesarlo como antes
                    $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoBase64));
                    $tempPathLogo = storage_path('app/public/temp_logo_' . uniqid() . '.png');
                    file_put_contents($tempPathLogo, $logoData);
                }
            }

            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Cargar y mostrar logo si está presente
            if ($tempPathLogo) {
                $imgLogo = EscposImage::load($tempPathLogo);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($imgLogo);
                $printer->feed(1);
            }

            // Cargar y mostrar imagen principal
            $img = EscposImage::load($tempPath);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->bitImage($img);
            $printer->feed(1);
            $printer->cut();

            if ($openCash) {
                $printer->pulse();
            }

            $printer->close();

            // Eliminar archivos temporales
            @unlink($tempPath);
            if ($tempPathLogo) {
                // Solo eliminar si no es un archivo de caché (archivos temporales contienen 'temp_logo_')
                if (strpos($tempPathLogo, 'temp_logo_') !== false) {
                    @unlink($tempPathLogo); // Archivo temporal base64
                }
                // Los archivos de caché (logo_cache/) se mantienen para reutilización
            }

            return response()->json(['message' => 'Orden impresa correctamente'], 200);
        } catch (\Exception $e) {
            // Eliminar archivos temporales en caso de error
            @unlink($tempPath);
            if (isset($tempPathLogo) && $tempPathLogo && strpos($tempPathLogo, 'temp_logo_') !== false) {
                @unlink($tempPathLogo); // Solo archivos temporales base64
            }

            return response()->json(['message' => 'Error al imprimir la factura', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🚀 MÉTODO ULTRA RÁPIDO: Descargar logo de empresa desde URL (this.company.logo)
     */
    private function downloadLogoFromUrl($url)
    {
        try {
            $logoHash = md5($url);
            $cacheDir = storage_path('app/public/logo_cache');

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            // 🚀 CACHÉ PERMANENTE: Logo de empresa siempre el mismo
            $cachePath = $cacheDir . '/company_logo_' . $logoHash . '.png';

            // 🚀 ULTRA RÁPIDO: Si ya existe en caché permanente, devolver inmediatamente
            if (file_exists($cachePath)) {
                return $cachePath;
            }

            // 🚀 OPTIMIZACIÓN: Descargar con timeout corto solo la primera vez
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $logoData = file_get_contents($url, false, $context);

            if ($logoData !== false) {
                file_put_contents($cachePath, $logoData);
                return $cachePath;
            }

            return null;
        } catch (\Exception $e) {
            return null;
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
