<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        . '{--insecure : Disable TLS peer verification (for debugging only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authenticate and connect to ws.gridpos.co; log all incoming WebSocket messages';

    public function handle(): int
    {
        $apiKey = config('app.ws.api_key');
        $userId = $this->option('userId') ?: config('app.ws.user_id');
        $businessId = $this->option('businessId') ?: config('app.ws.business_id');
        $role = $this->option('role') ?: config('app.ws.role');
        $authUrl = $this->option('auth') ?: config('app.ws.auth_url');
        $wsUrl = $this->option('url') ?: config('app.ws.url');

        if (empty($apiKey) || empty($userId) || empty($businessId)) {
            $this->error('Missing required configuration: WS_API_KEY, WS_USER_ID, WS_BUSINESS_ID');
            return 1;
        }

        try {
            $this->line('Requesting token...');
            $resp = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($authUrl, [
                'userId' => $userId,
                'businessId' => $businessId,
                'role' => $role,
            ]);

            if (!$resp->ok()) {
                $this->error('Auth failed: ' . $resp->status());
                Log::error('WS auth failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return 1;
            }

            $json = $resp->json();
            $token = $json['token'] ?? null;
            if (empty($token)) {
                $this->error('Auth response missing token');
                Log::error('WS auth missing token', ['json' => $json]);
                return 1;
            }

            $this->info('Token acquired. Connecting to WebSocket...');
            $this->line('Auth URL: ' . $authUrl);
            $this->line('WS URL: ' . $wsUrl);
            $this->line('User: ' . $userId . ' | Business: ' . $businessId . ' | Role: ' . $role);

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
            $this->line('Using header Authorization: Bearer ' . substr($token, 0, 12) . '...');

            $connector($wsUrl, [], $headers)
                ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop) {
                    $this->info('WebSocket connected. Listening...');

                    $conn->on('message', function ($msg) use ($conn) {
                        $text = (string) $msg;
                        Log::info('WS message', ['message' => $text]);
                    });

                    $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                        Log::warning('WS closed', ['code' => $code, 'reason' => $reason]);
                        $loop->stop();
                    });

                    $conn->on('error', function ($e) use ($loop) {
                        Log::error('WS error', ['error' => $e instanceof \Throwable ? $e->getMessage() : $e]);
                        $loop->stop();
                    });
                }, function ($e) use ($loop, $connector, $wsUrl, $token) {
                    $message = $e instanceof \Throwable ? $e->getMessage() : (string) $e;
                    Log::error('WS connect failed (header auth)', ['error' => $message]);
                    if ($this->getOutput()->isVerbose()) {
                        $this->error('WebSocket connection failed: ' . $message);
                    }

                    $fallbackUrl = $wsUrl . (strpos($wsUrl, '?') === false ? '?' : '&') . 'token=' . urlencode($token);
                    if ($this->getOutput()->isVerbose()) {
                        $this->line('Retrying with token in query string: ' . $fallbackUrl);
                    } else {
                        $this->line('Retrying with token in query string...');
                    }

                    $connector($fallbackUrl)
                        ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop) {
                            $this->info('WebSocket connected (query token). Listening...');

                            $conn->on('message', function ($msg) use ($conn) {
                                $text = (string) $msg;
                                Log::info('WS message', ['message' => $text]);
                            });

                            $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                                Log::warning('WS closed', ['code' => $code, 'reason' => $reason]);
                                $loop->stop();
                            });

                            $conn->on('error', function ($e) use ($loop) {
                                Log::error('WS error', ['error' => $e instanceof \Throwable ? $e->getMessage() : $e]);
                                $loop->stop();
                            });
                        }, function ($e2) use ($loop, $connector, $wsUrl, $token) {
                            $message2 = $e2 instanceof \Throwable ? $e2->getMessage() : (string) $e2;
                            Log::error('WS connect failed (query auth)', ['error' => $message2]);
                            if ($this->getOutput()->isVerbose()) {
                                $this->error('WebSocket connection failed (query): ' . $message2);
                            }

                            // Third attempt: Socket.IO Engine.IO websocket endpoint
                            $base = rtrim($wsUrl, '/');
                            $socketIoUrl = $base . '/socket.io/?EIO=4&transport=websocket&token=' . urlencode($token);
                            if ($this->getOutput()->isVerbose()) {
                                $this->line('Retrying Socket.IO websocket endpoint: ' . $socketIoUrl);
                            } else {
                                $this->line('Retrying Socket.IO websocket endpoint...');
                            }

                            $connector($socketIoUrl)
                                ->then(function (\Ratchet\Client\WebSocket $conn) use ($loop, $token) {
                                    $this->info('Connected to Socket.IO websocket endpoint. Listening...');

                                    // Send Socket.IO namespace connect with auth token ("40" + JSON payload)
                                    try {
                                        $payload = json_encode(['token' => $token]);
                                    } catch (\Throwable $e) {
                                        $payload = '{}';
                                    }
                                    $conn->send('40' . $payload);

                                    $conn->on('message', function ($msg) use ($conn) {
                                        $text = (string) $msg;
                                        Log::info('WS message', ['message' => $text]);

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
                                        /**
                                         * TODO: Diego aca es donde viene el payload del websocket
                                         * aca deberia poder llamar una funcion que tome el payload y haga lo que tenga que hacer
                                         * pero que sea una funcion aparte, es decir podria llamar la funcion processSalePrint
                                         * y a futuro que no llame el controlador que llame una clase, porque llamando al controlador
                                         * abre un canal request que consume mucha mas memoria.
                                         **/
                                        if (strncmp($text, '42', 2) === 0) {
                                            $jsonPart = substr($text, 2);
                                            try {
                                                $arr = json_decode($jsonPart, true);
                                                if (is_array($arr) && count($arr) >= 1) {
                                                    $eventName = $arr[0];
                                                    $eventPayload = $arr[1] ?? null;
                                                    Log::info('Socket.IO event', ['event' => $eventName, 'payload' => $eventPayload]);
                                                }
                                            } catch (\Throwable $e) {
                                                Log::warning('Failed to parse Socket.IO event', ['data' => $jsonPart, 'error' => $e->getMessage()]);
                                            }
                                            return;
                                        }
                                    });

                                    $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                                        Log::warning('WS closed', ['code' => $code, 'reason' => $reason]);
                                        $loop->stop();
                                    });

                                    $conn->on('error', function ($e) use ($loop) {
                                        Log::error('WS error', ['error' => $e instanceof \Throwable ? $e->getMessage() : $e]);
                                        $loop->stop();
                                    });
                                }, function ($e3) use ($loop) {
                                    $message3 = $e3 instanceof \Throwable ? $e3->getMessage() : (string) $e3;
                                    Log::error('WS connect failed (socket.io endpoint)', ['error' => $message3]);
                                    $this->error('WebSocket connection failed (socket.io endpoint): ' . $message3);
                                    $loop->stop();
                                });
                        });
                });

            $loop->run();
            return 0;
        } catch (\Throwable $e) {
            Log::error('WS listener fatal error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Fatal error: ' . $e->getMessage());
            return 1;
        }
    }
}


