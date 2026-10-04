<?php
namespace App\Http\Controllers;

use App\Models\Streamer;
use App\Services\DonationAlertsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConnectController extends Controller
{
    public function __construct(private DonationAlertsClient $da) {}

    public function home()
    {
        return view('home');
    }

    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('da_state', $state);

        return redirect($this->da->authorizeUrl($state));
    }

    public function callback(Request $request)
    {
        abort_unless(
            $request->filled('code') && hash_equals((string) $request->session()->pull('da_state'), (string) $request->state),
            403, 'Ошибка авторизации'
        );

        $tokens = $this->da->exchangeCode($request->code);
        $user = $this->da->user($tokens['access_token'])['data'];

        $streamer = Streamer::firstOrNew(['da_user_id' => $user['id']]);
        $streamer->fill([
            'name'             => $user['name'] ?? $user['code'],
            'avatar'           => $user['avatar'] ?? null,
            'access_token'     => $tokens['access_token'],
            'refresh_token'    => $tokens['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            'last_error'    => null,
            'last_error_at' => null,
        ]);
        $streamer->widget_token ??= Str::random(40);
        $streamer->save();

        $request->session()->put('streamer_id', $streamer->id);

        return redirect()->route('me');
    }
}
