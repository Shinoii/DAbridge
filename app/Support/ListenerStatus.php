<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ListenerStatus
{
    public static function age(): ?int
    {
        $t = Cache::get('da_listener_heartbeat');

        return $t ? time() - (int) $t : null;
    }

    public static function alive(): bool
    {
        $age = self::age();

        return $age !== null && $age < 60;
    }
}
