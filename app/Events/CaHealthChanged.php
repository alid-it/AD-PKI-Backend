<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaHealthChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $component;
    public string $status;
    public ?string $url;

    public function __construct(string $component, string $status, ?string $url = null)
    {
        $this->component = $component;
        $this->status    = $status;
        $this->url       = $url;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('system-health'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CaHealthChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'component' => $this->component,
            'status'    => $this->status,
            'url'       => $this->url,
        ];
    }
}