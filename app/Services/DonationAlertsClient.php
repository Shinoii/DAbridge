<?php
namespace App\Services;

use App\Models\Streamer;
use Illuminate\Support\Facades\Http;

class DonationAlertsClient
{
    private const BASE = 'https://www.donationalerts.com';

    public function authorizeUrl(string $state): string
    {
        return self::BASE.'/oauth/authorize?'.http_build_query([
                'client_id'     => config('services.donationalerts.client_id'),
                'redirect_uri'  => config('services.donationalerts.redirect'),
                'response_type' => 'code',
                'scope'         => 'oauth-user-show oauth-donation-subscribe',
                'state'         => $state,
            ]);
    }

    public function exchangeCode(string $code): array
    {
        return Http::asForm()->post(self::BASE.'/oauth/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => config('services.donationalerts.client_id'),
            'client_secret' => config('services.donationalerts.client_secret'),
            'redirect_uri'  => config('services.donationalerts.redirect'),
            'code'          => $code,
        ])->throw()->json();
    }

    public function refresh(string $refreshToken): array
    {
        return Http::asForm()->post(self::BASE.'/oauth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => config('services.donationalerts.client_id'),
            'client_secret' => config('services.donationalerts.client_secret'),
            'scope'         => 'oauth-user-show oauth-donation-subscribe',
        ])->throw()->json();
    }

    public function user(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::BASE.'/api/v1/user/oauth')->throw()->json();
    }

    public function subscribeToken(string $accessToken, string $clientId, string $channel): string
    {
        $res = Http::withToken($accessToken)->post(self::BASE.'/api/v1/centrifuge/subscribe', [
            'channels' => [$channel],
            'client'   => $clientId,
        ])->throw()->json();

        return $res['channels'][0]['token'];
    }

    public function validToken(Streamer $s): string
    {
        if ($s->token_expires_at && $s->token_expires_at->subMinutes(5)->isPast() && $s->refresh_token) {
            $t = $this->refresh($s->refresh_token);
            $s->update([
                'access_token'     => $t['access_token'],
                'refresh_token'    => $t['refresh_token'] ?? $s->refresh_token,
                'token_expires_at' => now()->addSeconds($t['expires_in'] ?? 3600),
            ]);
        }

        return $s->access_token;
    }
}
