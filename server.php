<?php

/**
 * Servidor ligero para consultas continuas
 * Este script ejecuta consultas continuas a la API sin la sobrecarga de Laravel
 */

// Configuración
$sleepMicroseconds = 100000; // 0.1 segundos - ajustar según necesidad
$apiUrl = "https://api.gridpos.co/print-queue";
$authToken = 'f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3';

// Obtener slug desde el archivo .env
$envFile = __DIR__ . '/.env';
$apiUrlPos = '';

if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'API_URL_POS=') === 0) {
            $apiUrlPos = trim(substr($line, strlen('API_URL_POS=')));
            break;
        }
    }
}

echo "Iniciando servidor de impresión rápido...\n";
echo "API URL POS: $apiUrlPos\n";
echo "Intervalo: " . ($sleepMicroseconds / 1000000) . " segundos\n";

// Ruta a artisan para ejecutar comandos
$artisanPath = __DIR__ . '/artisan';

// Bucle infinito para consultas continuas
while (true) {
    try {
        // Configurar opciones para curl
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authToken,
            'X-Client-Slug: ' . $apiUrlPos,
        ]);

        // Ejecutar la consulta
        $response = curl_exec($ch);
        $data = json_decode($response, true);

        // Cerrar curl
        curl_close($ch);

        // Procesar datos si existen
        if (!empty($data)) {
            foreach ($data as $value) {
                // Ejecutar el comando db:check-table para procesar este elemento
                // Esto aprovecha la lógica existente en Laravel
                exec("php $artisanPath db:check-table");
                // Solo necesitamos ejecutar una vez, el comando se encargará del resto
                break;
            }
        }

        // Pausa muy breve para no saturar CPU
        usleep($sleepMicroseconds);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        // Pausa antes de reintentar en caso de error
        sleep(1);
    }
}
