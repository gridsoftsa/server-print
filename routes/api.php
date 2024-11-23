<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

Route::middleware(['cors'])->group(
    function () {
        Route::get('open-drawer/{name}', 'App\Http\Controllers\PrinterController@openCash');
        Route::post('print-order', 'App\Http\Controllers\PrinterController@printOrder');
        Route::post('print-sale', 'App\Http\Controllers\PrinterController@printSale');
    }
);
