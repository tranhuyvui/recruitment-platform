<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagesMarkedAsRead implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $readerId;
    private $senderId; // Là ID của người gửi (người sẽ nhận được thông báo "Đã xem")

    public function __construct($readerId, $senderId)
    {
        $this->readerId = $readerId;
        $this->senderId = $senderId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->senderId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages_marked_as_read';
    }
}
