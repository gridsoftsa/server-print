<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */


    'paths' => ['api/*', '*'], // Aplica CORS a todas las rutas o específicas si prefieres

    'allowed_methods' => ['*'], // Permite todos los métodos HTTP (GET, POST, PUT, DELETE, etc.)

    'allowed_origins' => ['*'], // Permite cualquier origen

    'allowed_origins_patterns' => [], // No se requieren patrones adicionales

    'allowed_headers' => ['*'], // Permite todos los encabezados

    'exposed_headers' => [], // Puedes agregar encabezados específicos si es necesario

    'max_age' => 0,

    'supports_credentials' => true, // Habilita las cookies y autenticación si es necesario

];
