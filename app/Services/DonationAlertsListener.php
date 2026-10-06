<?php
namespace App\Services;

use App\Events\DonationReceived;
use App\Models\Donation;
use App\Models\Streamer;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
class DonationAlertsListener
{
    private const WS_URL = 'wss://centrifugo.donationalerts.com/connection/websocket';

    private array $conns = [];   // streamer_id => true (подключаемся) | WebSocket
    private array $retryAt = []; // streamer_id => timestamp

    public function __construct(private LoopInterface $loop, private DonationAlertsClient $api) {}

    /** Синхронизирует соединения со списком активных стримеров. */
    public function sync(): void
    {
        $active = Streamer::where('is_active', true)->get()->keyBy('id');

        foreach ($this->conns as $id => $conn) {
            if (! $active->has($id)) {
                if ($conn instanceof WebSocket) {
                    $conn->close();
                }
                unset($this->conns[$id]);
                Streamer::whereKey($id)->update(['is_connected' => false]);
            }
        }

        foreach ($active as $id => $streamer) {
            if (isset($this->conns[$id]) || ($this->retryAt[$id] ?? 0) > time()) {
                continue;
            }
            $this->connect($streamer);
        }
    }

    private function connect(Streamer $s): void
    {
        $this->conns[$s->id] = true;

        try {
            $token = $this->api->validToken($s);
            $user  = $this->api->user($token)['data'];
        } catch (\Throwable $e) {
            $this->fail($s, $e);
            return;
        }

        (new Connector($this->loop))(self::WS_URL)->then(
            function (WebSocket $ws) use ($s, $user, $token) {
                $ws->on('close', function () use ($s) {
                    unset($this->conns[$s->id]);
                    $this->retryAt[$s->id] = time() + 10;
                    $s->update([
                        'is_connected'  => false,
                        'last_error'    => 'Соединение с DonationAlerts разорвано. Переподключаемся.',
                        'last_error_at' => now(),
                    ]);
                });
                // ...
            },
            fn ($e) => $this->fail($s, $e)
        );
    }

    private function fail(Streamer $s, \Throwable|string $e): void
    {
        $raw = $e instanceof \Throwable ? $e->getMessage() : $e;
        $msg = $e instanceof \Throwable ? $this->explain($e) : $e;

        Log::warning("DA connect failed [{$s->name}]: {$raw}");
        unset($this->conns[$s->id]);
        $this->retryAt[$s->id] = time() + 30;
        $s->update(['is_connected' => false, 'last_error' => $msg, 'last_error_at' => now()]);
    }

    private function explain(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $code = $e->response->status();
            if (in_array($code, [400, 401, 403], true)) {
                return 'DonationAlerts отклонил доступ: токен недействителен или отозван. Войдите заново.';
            }
            if ($code === 429) {
                return 'DonationAlerts временно ограничил запросы. Повторяем попытку.';
            }
            if ($code >= 500) {
                return 'DonationAlerts временно недоступен. Повторяем попытку.';
            }
        }
        if ($e instanceof ConnectionException) {
            return 'Не удаётся связаться с DonationAlerts. Повторяем попытку.';
        }

        return 'Не удалось подключиться к DonationAlerts. Повторяем попытку.';
    }

    private function onMessage(WebSocket $ws, Streamer $s, int $daId, string $token, string $raw): void
    {
        Log::info("DA raw [{$s->name}]: ".$raw);
        try {
            $data = json_decode($raw, true);

            // Пинг от сервера — отвечаем таким же пустым сообщением
            if ($data === []) {
                $ws->send('{}');
                return;
            }

            if (isset($data['error'])) {
                Log::warning("DA error [{$s->name}]: ".json_encode($data['error']));
                $s->update([
                    'last_error'    => 'DonationAlerts вернул ошибку при подключении. Если не проходит, войдите заново.',
                    'last_error_at' => now(),
                ]);
                return;
            }

            // Ответ на connect → подписываемся на канал донатов
            if (($data['id'] ?? null) === 1 && isset($data['result']['client'])) {
                $channel = '$alerts:donation_'.$daId;
                $subToken = $this->api->subscribeToken($token, $data['result']['client'], $channel);
                $ws->send(json_encode([
                    'params' => ['channel' => $channel, 'token' => $subToken],
                    'method' => 1,
                    'id'     => 2,
                ]));
                return;
            }

            // Подписка подтверждена
            if (($data['id'] ?? null) === 2) {
                $s->update([
                    'is_connected'      => true,
                    'last_connected_at' => now(),
                    'last_error'        => null,
                    'last_error_at'     => null,
                ]);
                return;
            }

            // Новый донат
            if (isset($data['result']['channel'], $data['result']['data']['data'])) {
                $this->handleDonation($s, $data['result']['data']['data']);
            }
        } catch (\Throwable $e) {
            Log::error("DA message error [{$s->name}]: ".$e->getMessage());
        }
    }

    private function handleDonation(Streamer $s, array $d): void
    {
        $donation = Donation::firstOrCreate(
            ['streamer_id' => $s->id, 'da_id' => $d['id']],
            [
                'username' => $d['username'] ?? null,
                'message'  => $d['message'] ?? null,
                'amount'   => $d['amount'] ?? 0,
                'currency' => $d['currency'] ?? 'RUB',
                'raw'      => $d,
            ]
        );

        if (! $donation->wasRecentlyCreated) {
            return; // дубль
        }

        $s->increment('donations_count');
        $s->update(['last_donation_at' => now()]);

        event(new DonationReceived($s->widget_token, [
            'id'          => $donation->da_id,
            'username'    => $donation->username ?: 'Аноним',
            'message'     => $donation->message,
            'amount'      => (float) $donation->amount,
            'currency'    => $donation->currency,
            'amount_main' => $d['amount_in_user_currency'] ?? null,
            'created_at'  => $d['created_at'] ?? now()->toIso8601String(),
        ]));
    }
}
