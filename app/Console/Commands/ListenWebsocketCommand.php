<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\PrinterService;
use Ratchet\Client\Connector as RatchetConnector;
use React\EventLoop\Factory as LoopFactory;

class ListenWebsocketCommand extends Command
{
    /**
     * The name and signature of the console command.
     * add the -v option to the command for verbose output
     *
     * @var string
     */
    protected $signature = 'ws:listen '
        . '{--userId= : User ID for auth}'
        . '{--businessId= : Business ID for auth}'
        . '{--role=user : Role for auth}'
        . '{--url= : WebSocket URL (overrides env)}'
        . '{--auth= : Auth URL (overrides env)}'
        . '{--insecure : Disable TLS peer verification (for debugging only)}'
        . '{--max-retries=0 : Maximum retry attempts (0 = infinite)}'
        . '{--retry-delay=5 : Initial retry delay in seconds}'
        . '{--max-delay=300 : Maximum retry delay in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authenticate and connect to ws.gridpos.co; log all incoming WebSocket messages with auto-reconnect';

    /**
     * Connection retry state
     */
    private int $retryCount = 0;
    private int $maxRetries;
    private int $retryDelay;
    private int $maxDelay;
    private bool $shouldReconnect = true;

    public function handle(): int
    {
        // Initialize retry configuration
        $this->maxRetries = (int) $this->option('max-retries');
        $this->retryDelay = (int) $this->option('retry-delay');
        $this->maxDelay = (int) $this->option('max-delay');
        $this->shouldReconnect = true;

        $apiKey = config('app.ws.api_key');
        $userId = $this->option('userId') ?: config('app.ws.user_id'); //Es el slug del cliente "matambre"
        $businessId = $this->option('businessId') ?: config('app.ws.business_id'); //Es el canal de impresion "matambre-server-print"
        $role = $this->option('role') ?: config('app.ws.role');
        $authUrl = $this->option('auth') ?: config('app.ws.auth_url');
        $wsUrl = $this->option('url') ?: config('app.ws.url');

        if (empty($apiKey) || empty($userId) || empty($businessId)) {
            $this->error('Missing required configuration: WS_API_KEY, WS_USER_ID, WS_BUSINESS_ID');
            return 1;
        }

        // Setup signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }

        $this->info('Starting WebSocket listener...');

        return $this->connectWithRetry($apiKey, $userId, $businessId, $role, $authUrl, $wsUrl);
    }

    private function connectWithRetry(string $apiKey, string $userId, string $businessId, string $role, string $authUrl, string $wsUrl): int
    {
        while ($this->shouldReconnect) {
            try {
                $result = $this->attemptConnection($apiKey, $userId, $businessId, $role, $authUrl, $wsUrl);
                if ($result === 0) {
                    // Connection successful, reset retry count
                    $this->retryCount = 0;
                    return 0;
                }
            } catch (\Throwable $e) {
                // Silent retry
            }

            // Check if we should retry
            if ($this->maxRetries > 0 && $this->retryCount >= $this->maxRetries) {
                $this->error('Connection failed. Maximum retries reached.');
                return 1;
            }

            if ($this->shouldReconnect) {
                $this->retryCount++;
                $delay = min($this->retryDelay * pow(2, $this->retryCount - 1), $this->maxDelay);
                sleep($delay);
            }
        }

        return 0;
    }

    private function attemptConnection(string $apiKey, string $userId, string $businessId, string $role, string $authUrl, string $wsUrl): int
    {
        try {
            $resp = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($authUrl, [
                'userId' => $userId,
                'businessId' => $businessId,
                'role' => $role,
            ]);

            if (!$resp->ok()) {
                return 1;
            }

            $json = $resp->json();
            $token = $json['token'] ?? null;
            if (empty($token)) {
                return 1;
            }

            $loop = LoopFactory::create();
            $insecure = (bool) $this->option('insecure');
            $reactConnector = new \React\Socket\Connector($loop, [
                'timeout' => 10,
                'tls' => [
                    'verify_peer' => !$insecure,
                    'verify_peer_name' => !$insecure,
                    'SNI_enabled' => true,
                ],
            ]);

            $connector = new RatchetConnector($loop, $reactConnector);

            $headers = [
                'Authorization' => 'Bearer ' . $token,
            ];

            $connector($wsUrl, [], $headers)
                ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop) {
                    $this->info('✅ WebSocket connected');

                    $conn->on('message', function ($msg) use ($conn) {
                        $text = (string) $msg;
                        // Process messages silently
                    });

                    $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                        $this->warn('❌ WebSocket disconnected');
                        $this->shouldReconnect = true;
                        $loop->stop();
                    });

                    $conn->on('error', function ($e) use ($loop) {
                        $this->warn('❌ WebSocket error');
                        $this->shouldReconnect = true;
                        $loop->stop();
                    });
                }, function ($e) use ($loop, $connector, $wsUrl, $token) {
                    $fallbackUrl = $wsUrl . (strpos($wsUrl, '?') === false ? '?' : '&') . 'token=' . urlencode($token);

                    $connector($fallbackUrl)
                        ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop) {
                            $this->info('✅ WebSocket connected');

                            $conn->on('message', function ($msg) use ($conn) {
                                $text = (string) $msg;
                                // Process messages silently
                            });

                            $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                                $this->warn('❌ WebSocket disconnected');
                                $this->shouldReconnect = true;
                                $loop->stop();
                            });

                            $conn->on('error', function ($e) use ($loop) {
                                $this->warn('❌ WebSocket error');
                                $this->shouldReconnect = true;
                                $loop->stop();
                            });
                        }, function ($e2) use ($loop, $connector, $wsUrl, $token) {
                            // Third attempt: Socket.IO Engine.IO websocket endpoint
                            $base = rtrim($wsUrl, '/');
                            $socketIoUrl = $base . '/socket.io/?EIO=4&transport=websocket&token=' . urlencode($token);

                            $connector($socketIoUrl)
                                ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop, $token) {
                                    $this->info('✅ WebSocket connected');

                                    // Send Socket.IO namespace connect with auth token ("40" + JSON payload)
                                    try {
                                        $payload = json_encode(['token' => $token]);
                                    } catch (\Throwable $e) {
                                        $payload = '{}';
                                    }
                                    $conn->send('40' . $payload);

                                    $conn->on('message', function ($msg) use ($conn) {
                                        $text = (string) $msg;

                                        // Engine.IO open packet
                                        if (strlen($text) > 0 && $text[0] === '0') {
                                            return;
                                        }

                                        // Engine.IO ping -> pong
                                        if ($text === '2') {
                                            $conn->send('3');
                                            return;
                                        }

                                        // Socket.IO event packet: 42["event", {...}]
                                        if (strncmp($text, '42', 2) === 0) {
                                            $jsonPart = substr($text, 2);
                                            try {
                                                $arr = json_decode($jsonPart, true);
                                                if (is_array($arr) && count($arr) >= 1) {
                                                    $eventName = $arr[0];
                                                    $eventPayload = $arr[1] ?? null;

                                                    // Integración con PrinterService
                                                    if ($eventName === 'business-event' && is_array($eventPayload)) {
                                                        $data = $eventPayload['data'] ?? $eventPayload;
                                                        $action = $data['action'] ?? ($eventPayload['action'] ?? null);
                                                        try {
                                                            $printerService = app(PrinterService::class);
                                                            if ($action === 'salePrinter') {
                                                                $this->processSalePrint($printerService, $data);
                                                            } elseif ($action === 'orderPrinter') {
                                                                $this->processOrderPrint($printerService, $data);
                                                            } elseif ($action === 'openCashDrawer') {
                                                                $printer = $data['printer'] ?? env('DEFAULT_PRINTER', 'POS-80');
                                                                $printerService->openCash($printer);
                                                            }
                                                        } catch (\Throwable $e) {
                                                            // Silent error handling
                                                        }
                                                    }
                                                }
                                            } catch (\Throwable $e) {
                                                // Silent error handling
                                            }
                                            return;
                                        }
                                    });

                                    $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                                        $this->warn('❌ WebSocket disconnected');
                                        $this->shouldReconnect = true;
                                        $loop->stop();
                                    });

                                    $conn->on('error', function ($e) use ($loop) {
                                        $this->warn('❌ WebSocket error');
                                        $this->shouldReconnect = true;
                                        $loop->stop();
                                    });
                                }, function ($e3) use ($loop) {
                                    $this->shouldReconnect = true;
                                    $loop->stop();
                                });
                        });
                });

            $loop->run();
            return 0;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Handle shutdown signals gracefully
     */
    public function handleShutdown(): void
    {
        $this->info('Stopping...');
        $this->shouldReconnect = false;
    }

    private function processSalePrint(PrinterService $printerService, $value): void
    {
        try {
            $data = [
                'printerName' => $value['printer'] ?? ($value['printerName'] ?? env('DEFAULT_PRINTER', 'POS-80')),
                'base64Image' => $value['image'] ?? ($value['base64Image'] ?? null),
                'logoBase64' => $value['logo_base64'] ?? ($value['logoBase64'] ?? null),
                'logo' => $value['logo'] ?? null,
                'openCash' => $value['open_cash'] ?? ($value['openCash'] ?? false),
                'useImage' => $value['print_settings']['use_image'] ?? ($value['useImage'] ?? false),
                'dataJson' => $value['data_json'] ?? ($value['dataJson'] ?? null),
                'company' => $value['company'] ?? ($value['data_json']['company'] ?? null),
            ];

            if (empty($data['logoBase64'])) {
                $printerService->printSale(
                    (string) $data['printerName'],
                    (string) ($data['base64Image'] ?? ''),
                    (bool) $data['openCash'],
                    $data['logoBase64'],
                    $data['logo']
                );
            } elseif (!empty($data['dataJson']) && $data['useImage'] === false) {
                $printerService->printSaleEscPos(
                    (string) $data['printerName'],
                    (array) $data['dataJson'],
                    (bool) $data['openCash'],
                    $data['company'],
                    (string) $data['logoBase64']
                );
            } else {
                $printerService->printSale(
                    (string) $data['printerName'],
                    (string) ($data['base64Image'] ?? ''),
                    (bool) $data['openCash'],
                    $data['logoBase64'],
                    $data['logo']
                );
            }
        } catch (\Throwable $e) {
            // Silent error handling
        }
    }

    private function processOrderPrint(PrinterService $printerService, $value): void
    {
        try {
            $data = [
                'printerName' => $value['printer'] ?? ($value['printerName'] ?? env('DEFAULT_PRINTER', 'POS-80')),
                'orderData' => $value['data_json'] ?? ($value['orderData'] ?? []),
                'openCash' => $value['open_cash'] ?? ($value['openCash'] ?? false),
            ];

            $printerService->printOrder(
                (string) $data['printerName'],
                (array) $data['orderData'],
                (bool) $data['openCash']
            );
        } catch (\Throwable $e) {
            // Silent error handling
        }
    }
}
