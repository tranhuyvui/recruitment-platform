<?php

namespace App\Services;

use App\Models\MessageModel;

class MessageService
{
    public function getConversations(int $myId)
    {
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
                // ✅ Sort TRƯỚC khi group để $last lấy đúng tin mới nhất
                ['$sort' => ['created_at' => 1]],
                [
                    '$group' => [
                        '_id' => [
                            '$cond' => [
                                ['$eq' => ['$sender_id', $myId]],
                                '$receiver_id',
                                '$sender_id'
                            ]
                        ],
                        'latestMessage' => ['$last' => '$content'],
                        'timestamp' => ['$last' => '$created_at'],
                        'lastSenderId' => ['$last' => '$sender_id'],
                        'sender_name' => ['$last' => '$sender_name'],
                        'sender_avatar' => ['$last' => '$sender_avatar'],
                        'receiver_name' => ['$last' => '$receiver_name'],
                        'receiver_avatar' => ['$last' => '$receiver_avatar'],
                        'isRead' => ['$last' => '$is_read']
                    ]
                ],
                // Sort lại sau group để conversation mới nhất lên đầu
                ['$sort' => ['timestamp' => -1]],
                ['$limit' => 20]
            ]);
        });
        return collect($conversations)->map(function ($c) use ($myId) {
            return [
                'userId' => $c['_id'],
                'latestMessage' => $c['latestMessage'],
                // 'timestamp' => $c['timestamp']->toDateTime()->format('c'),
                'timestamp' => isset($c['timestamp']) ? $c['timestamp']?->toDateTime()?->format('c') : null,
                'lastSenderId' => $c['lastSenderId'],
                'isRead' => $c['isRead'],
                'name' => $c['lastSenderId'] === $myId ? $c['receiver_name'] : $c['sender_name'],
                'avatar' => $c['lastSenderId'] === $myId ? $c['receiver_avatar'] : $c['sender_avatar']
            ];
        });
    }

    public function getChatHistory(int $myId, int $otherUserId, int $limit = 50, int $page = 1)
    {
        return MessageModel::where(function ($query) use ($myId, $otherUserId) {
            $query->where('sender_id', $myId)
                ->where('receiver_id', $otherUserId);
        })
            ->orWhere(function ($query) use ($myId, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                    ->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function getCountUnreadMessages(int $myId)
    {
        $result = MessageModel::raw(function ($collection) use ($myId) {
            return $collection->aggregate([
                ['$match' => ['receiver_id' => $myId, 'is_read' => false]],
                ['$group' => ['_id' => '$sender_id']],
                ['$count' => 'total']
            ]);
        });

        return collect($result)->first()['total'] ?? 0;
    }
}
