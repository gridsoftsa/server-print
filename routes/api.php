<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('open-drawer', function () {
    Log::info('openDrawer');
    $connector = new WindowsPrintConnector('POS-80');

    $printer = new Printer($connector);

    try {
        // Comando ESC/POS para abrir la caja registradora
        $printer->pulse();
        $printer->close();

        return response()->json(['message' => 'Caja abierta'], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al abrir la caja', 'error' => $e->getMessage()], 500);
    }
});
