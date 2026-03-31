<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## WebSocket listener (server-print)

This project includes an Artisan command that authenticates against `https://ws.gridpos.co/api/auth/token` and connects to `wss://ws.gridpos.co`, logging all incoming messages.

### Configure

Set the following environment variables in your `.env`:

- `WS_API_KEY`: API key provided by DevOps (required)
- `WS_URL`: WebSocket URL, default `wss://ws.gridpos.co`
- `WS_AUTH_URL`: Auth endpoint, default `https://ws.gridpos.co/api/auth/token`
- `WS_USER_ID`: User identifier (required)
- `WS_BUSINESS_ID`: Business identifier (required)
- `WS_ROLE`: Role (default `user`)

### Run

```
php artisan ws:listen
```

You can override via options:

```
php artisan ws:listen \
  --userId=test_user_1 \
  --businessId=test_business_1 \
  --role=user \
  --url=wss://ws.gridpos.co \
  --auth=https://ws.gridpos.co/api/auth/token
```

Messages are logged to Laravel logs. To tail logs during development:

```
tail -f storage/logs/laravel.log | cat
```

## Sonido al imprimir orden (multi-marca)

`server-print` envía alerta sonora al final de `print-order` (solo ordenes, no ventas), con soporte multi-marca y retrocompatibilidad.

### Variables de entorno

Agrega en `.env`:

```
PRINT_ALERT_ENABLED=true
PRINT_ALERT_TIMES=2
PRINT_ALERT_DURATION_MS=120
PRINT_ALERT_PROFILE=generic
PRINT_ALERT_PROFILE_MAP='{"COCINA":"sat_q22","BARRA":"generic"}'
PRINT_ALERT_PROFILE_CONTAINS_MAP='{"sat":"sat_q22","q22":"sat_q22","q22ue":"sat_q22","epson":"generic","tm-t":"generic","tmu":"generic","3nstar":"generic","rpt":"generic","xprinter":"generic","xp-":"generic","bixolon":"generic","srp":"generic","star":"generic","tsp":"generic","gprinter":"generic","gp-":"generic","bematech":"generic","daruma":"generic"}'
PRINT_ALERT_BRAND_PROFILE_MAP='{"sat":"sat_q22","q22":"sat_q22","q22ue":"sat_q22","epson":"generic","3nstar":"generic","xprinter":"generic","bixolon":"generic","star":"generic","starmicronics":"generic","gprinter":"generic","bematech":"generic","daruma":"generic"}'
```

Perfiles soportados:
- `generic`: secuencia BEL + secuencias extendidas ESC/POS.
- `sat_q22`: prioriza comandos que suelen funcionar en SAT Q22UE y mantiene fallback.

Prioridad de selección de perfil:
1. `orderData.print_settings.print_alert_profile`
2. `orderData.print_settings.print_alert_brand` (o `printer_brand`/`brand`)
3. `PRINT_ALERT_PROFILE_MAP` por nombre exacto de impresora
4. `PRINT_ALERT_PROFILE_CONTAINS_MAP` por coincidencia parcial (`SATCOCINA`, `3NSTARCOCINA`, etc.)
5. Autodetección legacy por nombre (`sat`, `q22`, `q22ue`)
6. `PRINT_ALERT_PROFILE`
7. Fallback final: `generic`

### Configuración por orden (opcional)

Dentro de `orderData.print_settings` puedes enviar:
- `print_alert` o `beep_on_print`: `true|false`
- `print_alert_times` o `beep_times`: `1..9`
- `print_alert_duration_ms` o `beep_duration_ms`: `50..2000`
- `print_alert_profile`: `generic|sat_q22`
- `print_alert_brand` (o `printer_brand`/`brand`): `sat`, `q22ue`, `3nstar`, `epson`, etc.

### Ejemplos recomendados

```json
{
  "printerName": "SATCOCINA",
  "orderData": {
    "print_settings": {
      "print_alert": true
    }
  }
}
```

Con `PRINT_ALERT_PROFILE_CONTAINS_MAP`, `SATCOCINA` cae en `sat_q22`.

```json
{
  "printerName": "COCINA_01",
  "orderData": {
    "print_settings": {
      "print_alert": true,
      "print_alert_brand": "3nstar"
    }
  }
}
```

Ese request fuerza resolución por marca y mantiene retrocompatibilidad entre clientes.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
