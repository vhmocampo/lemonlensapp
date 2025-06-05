<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessful
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $priceId;
    public $userId;
    public $userEmail;
    public $sessionId;

    /**
     * Create a new event instance.
     *
     * @param string $priceId
     * @param int $userId
     * @param string $userEmail
     * @param string $sessionId
     */
    public function __construct(string $priceId, int $userId, string $userEmail, string $sessionId)
    {
        $this->priceId = $priceId;
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->sessionId = $sessionId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
