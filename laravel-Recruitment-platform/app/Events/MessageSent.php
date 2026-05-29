<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Thêm "implements ShouldBroadcastNow" để nó bắn real-time ngay lập tức
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $messageData;
    private $receiverId;

    public function __construct($messageData, $receiverId)
    {
        $this->messageData = $messageData;
        $this->receiverId = $receiverId;
    }

    // Tương đương io.to(receiver_id)
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->receiverId),
        ];
    }

    // Tương đương tên sự kiện 'receive_message'
    public function broadcastAs(): string
    {
        return 'receive_message';
    }
}
