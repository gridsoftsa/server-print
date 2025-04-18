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
    protected $description = 'Consulta el API continuamente para procesar trabajos de impresión';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $api_url_pos = env('API_URL_POS');
        $controller = app(PrinterController::class);
        $url = "https://api.gridpos.co/print-queue";
        $auth_token = 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3';

        try {
            $response = Http::withHeaders([
                'Authorization' => $auth_token,
                'X-Client-Slug' => $api_url_pos,
            ])->withoutVerifying()->get($url);

            $data_resp = $response->json();

            if (!empty($data_resp)) {
                foreach ($data_resp as $value) {
                    if ($value['action'] == 'openCashDrawer') {
                        $controller->openCash($value['printer']);
                        Http::withHeaders([
                            'Authorization' => $auth_token,
                            'X-Client-Slug' => $api_url_pos,
                        ])->withoutVerifying()->get($url . '/' . $value['id']);
                    } else if ($value['action'] == 'orderPrinter') {
                        $data = [
                            'printerName' => $value['printer'],
                            'image' => $value['image'],
                            'openCash' => $value['open_cash']
                        ];
                        $request = Request::create('/', 'GET', $data);
                        $controller->printOrder($request);
                        Http::withHeaders([
                            'Authorization' => $auth_token,
                            'X-Client-Slug' => $api_url_pos,
                        ])->withoutVerifying()->get($url . '/' . $value['id']);
                    } else if ($value['action'] == 'salePrinter') {
                        $data = [
                            'printerName' => $value['printer'],
                            'image' => $value['image'],
                            'logoBase64' => $value['logo'],
                            'openCash' => $value['open_cash']
                        ];
                        $request = Request::create('/', 'GET', $data);
                        $controller->printSale($request);
                        Http::withHeaders([
                            'Authorization' => $auth_token,
                            'X-Client-Slug' => $api_url_pos,
                        ])->withoutVerifying()->get($url . '/' . $value['id']);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en la consulta API: ' . $e->getMessage());
        }
    }
}
