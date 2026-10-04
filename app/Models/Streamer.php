<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Streamer extends Model
{
    protected $guarded = [];
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_connected_at'=> 'datetime',
            'last_donation_at' => 'datetime',
            'is_active'        => 'boolean',
            'is_connected'     => 'boolean',
            'last_error_at' => 'datetime'
        ];
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
