<?php

namespace App\Jobs;

use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushToUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public int $userId, public array $payload)
    {
    }

    public function handle(WebPushService $push): void
    {
        $push->sendToUser($this->userId, $this->payload);
    }
}
