<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class DonationReceived implements ShouldBroadcastNow
{
    public function __construct(public string $widgetToken, public array $donation) {}

    public function broadcastOn(): array
    {
        return [new Channel('donations.'.$this->widgetToken)];
    }

    public function broadcastAs(): string
    {
        return 'donation';
    }

    public function broadcastWith(): array
    {
        return $this->donation;
    }
}
