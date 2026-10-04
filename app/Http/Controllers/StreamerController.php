<?php
namespace App\Http\Controllers;

use App\Models\Streamer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Events\DonationReceived;
use App\Support\ListenerStatus;

class StreamerController extends Controller
{
    private function current(Request $request): ?Streamer
    {
        $id = $request->session()->get('streamer_id');

        return $id ? Streamer::find($id) : null;
    }

    public function show(Request $request)
    {
        $streamer = $this->current($request);

        if (! $streamer) {
            return redirect()->route('home');
        }

        return view('me', [
            'streamer'      => $streamer,
            'donations'     => $streamer->donations()->latest('id')->limit(30)->get(),
            'listenerAlive' => ListenerStatus::alive(),
        ]);
    }

    public function testDonation(Request $request)
    {
        $streamer = $this->current($request);
        abort_unless($streamer, 403);

        $data = $request->validate(['amount' => 'nullable|numeric|min:1|max:100000']);
        $amount = (float) ($data['amount'] ?? 100);

        try {
            event(new DonationReceived($streamer->widget_token, [
                'id'          => -time(),          // отрицательный id: не пересечётся с настоящими
                'username'    => 'Тест',
                'message'     => 'Тестовый донат',
                'amount'      => $amount,
                'currency'    => 'RUB',
                'amount_main' => $amount,
                'created_at'  => now()->toIso8601String(),
                'test'        => true,             // виджет может отличать тест от настоящего
            ]));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('err', 'Не удалось отправить: сокет-сервер недоступен.');
        }

        return back()->with('ok', 'Тестовый донат отправлен. Он не попадает в историю.');
    }

    public function regenerate(Request $request)
    {
        $streamer = $this->current($request);
        abort_unless($streamer, 403);

        $streamer->update(['widget_token' => Str::random(40)]);

        return redirect()->route('me')->with('ok', 'Новый токен создан. Обновите его в настройках виджета.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('streamer_id');

        return redirect()->route('home');
    }

    public function destroy(Request $request)
    {
        $streamer = $this->current($request);
        abort_unless($streamer, 403);

        $streamer->delete();
        $request->session()->invalidate();

        return redirect()->route('home')->with('ok', 'Аккаунт и все данные удалены.');
    }
}
