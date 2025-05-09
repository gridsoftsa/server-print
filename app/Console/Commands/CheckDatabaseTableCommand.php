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
        //\Log::info('Command execute succesfully');
        $api_url_pos = env('API_URL_POS');

        $controller = app(PrinterController::class);

        $url = "https://api.gridpos.co/print-queue";

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

        Log::info('Response from API: ', ['response' => $data_resp]);

        // Si response es un array vacío, terminar inmediatamente
        if (empty($data_resp)) {
            return 0;
        }

        Log::info('Imprimir o abrir caja');
        foreach ($data_resp as $key => $value) {
            //\Log::info($value['action']);
            if ($value['action'] == 'openCashDrawer') {
                $controller->openCash($value['printer']);
                $response = Http::withHeaders([
                    'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
                    'X-Client-Slug' => $api_url_pos,
                ])->withoutVerifying()->get($url . '/' . $value['id']);
            } else if ($value['action'] == 'orderPrinter') {
                // Definir el array de datos
                $data = [
                    'printerName' => $value['printer'],
                    'image' => $value['image'],
                    'openCash' => $value['open_cash']
                ];

                // Crear un objeto Request a partir del array
                $request = Request::create('/', 'GET', $data);

                // Llamar al controlador pasando el objeto Request
                $controller->printOrder($request);
                $response = Http::withHeaders([
                    'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
                    'X-Client-Slug' => $api_url_pos,
                ])->withoutVerifying()->get($url . '/' . $value['id']);
            } else if ($value['action'] == 'salePrinter') {
                // Definir el array de datos
                $data = [
                    'printerName' => $value['printer'],
                    'image' => $value['image'],
                    'logoBase64' => $value['logo'],
                    'openCash' => $value['open_cash']
                ];

                // Crear un objeto Request a partir del array
                $request = Request::create('/', 'GET', $data);

                // Llamar al controlador pasando el objeto Request
                $controller->printSale($request);
                $response = Http::withHeaders([
                    'Authorization' => 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3',
                    'X-Client-Slug' => $api_url_pos,
                ])->withoutVerifying()->get($url . '/' . $value['id']);
            }
        }
    }
}
