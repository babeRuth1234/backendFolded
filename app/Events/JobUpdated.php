<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $jobData;

    public function __construct(array $jobData)
    {
        $this->jobData = $jobData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'JobUpdated';
    }
}
