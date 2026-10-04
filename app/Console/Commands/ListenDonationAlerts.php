<?php
namespace App\Console\Commands;

use App\Models\Streamer;
use App\Services\DonationAlertsClient;
use App\Services\DonationAlertsListener;
use Illuminate\Console\Command;
use React\EventLoop\Loop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ListenDonationAlerts extends Command
{
    protected $signature = 'da:listen';
    protected $description = 'Держит WebSocket-соединения с DonationAlerts для всех активных стримеров';

    public function handle(): int
    {
        Streamer::query()->update(['is_connected' => false]);

        $loop = Loop::get();
        $listener = new DonationAlertsListener($loop, app(DonationAlertsClient::class));

        $tick = function () use ($listener) {
            try {
                Cache::put('da_listener_heartbeat', time(), 120); // сигнал «я жив» для админки
                $listener->sync();
            } catch (\Throwable $e) {
                Log::error('DA sync failed: '.$e->getMessage());   // демон не падает из-за одной ошибки
            }
        };

        $tick();
        $loop->addPeriodicTimer(15, $tick);

        $this->info('DA listener started');
        $loop->run();

        return self::SUCCESS;
    }
}
