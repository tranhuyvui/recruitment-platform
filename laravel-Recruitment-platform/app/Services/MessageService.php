<?php

namespace App\Services;

use App\Models\MessageModel;

class MessageService
{
    public function getConversations(int $myId)
    {
        // Hàm raw() cho phép chọc thẳng vào collection của MongoDB để chạy aggregate
        $conversations = MessageModel::raw(function ($collection) use ($myId) {
            return $collection->aggregate([
                [
                    '$match' => [
                        '$or' => [
                            ['sender_id' => $myId],
                            ['receiver_id' => $myId]
                        ]
                    ]
                ],
                ['$sort' => ['created_at' => -1]],
                [
                    '$group' => [
                        '_id' => [
                            '$cond' => [
                                ['$eq' => ['$sender_id', $myId]],
                                '$receiver_id',
                                '$sender_id'
                            ]
                        ],
                        'latestMessage' => ['$first' => '$content'],
                        'timestamp' => ['$first' => '$created_at'],
                        'lastSenderId' => ['$first' => '$sender_id'],
                        'sender_name' => ['$first' => '$sender_name'],
                        'sender_avatar' => ['$first' => '$sender_avatar'],
                        'receiver_name' => ['$first' => '$receiver_name'],
                        'receiver_avatar' => ['$first' => '$receiver_avatar'],
                        'isRead' => ['$first' => '$is_read']
                    ]
                ],
                ['$sort' => ['timestamp' => -1]]
            ]);
        });

        // Kết quả của aggregate trả về một Cursor, ta dùng collect() để biến nó thành mảng và map
        return collect($conversations)->map(function ($c) use ($myId) {
            return [
                'userId' => $c['_id'],
                'latestMessage' => $c['latestMessage'],
                'timestamp' => $c['timestamp']->toDateTime()->format('c'),
                'lastSenderId' => $c['lastSenderId'],
                'isRead' => $c['isRead'],
                'name' => $c['lastSenderId'] === $myId ? $c['receiver_name'] : $c['sender_name'],
                'avatar' => $c['lastSenderId'] === $myId ? $c['receiver_avatar'] : $c['sender_avatar']
            ];
        });
    }

    // 2. LẤY LỊCH SỬ CHAT (Dùng Query Builder chuẩn của Eloquent)
    public function getChatHistory(int $myId, int $otherUserId)
    {
        return MessageModel::where(function ($query) use ($myId, $otherUserId) {
            $query->where('sender_id', $myId)
                ->where('receiver_id', $otherUserId);
        })
            ->orWhere(function ($query) use ($myId, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                    ->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'asc') // Sắp xếp cũ nhất lên trước
            ->get();
    }

    public function getCountUnreadMessages(int $myId)
    {
        // Thay vì dùng aggregate cồng kềnh như Node.js, Laravel Eloquent làm việc này cực ngắn!
        // Dịch logic: Tìm các tin chưa đọc gửi cho mình -> Lấy ra danh sách các sender_id duy nhất -> Đếm.
        return MessageModel::where('receiver_id', $myId)
            ->where('is_read', false)
            ->distinct('sender_id') // Chỉ lấy id khác nhau
            ->count();
    }
}
