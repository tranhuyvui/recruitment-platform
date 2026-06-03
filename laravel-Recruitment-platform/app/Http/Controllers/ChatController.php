<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MessageModel; 
use App\Events\MessageSent;
use App\Events\MessagesMarkedAsRead;
use App\Services\MessageService;
use App\Services\AuthService;

use Exception;

class ChatController extends Controller
{
    protected $messageService;
    protected $authService;

    public function __construct(MessageService $messageService, AuthService $authService)
    {
        $this->messageService = $messageService;
        $this->authService = $authService;
    }
    // THAY THẾ CHO socket.on('send_message')
    public function sendMessage(Request $request)
    {
        try {
            $data = $request->validate([
                'receiver_id' => 'required|integer',
                'content' => 'required|string',
            ]);
            $user = $request->user();

            $sender = $this->authService->getProfile($user->UserID);
            $receiver = $this->authService->getProfile($data['receiver_id']);

            if (!$sender) {
                echo "Sender not found for UserID: " . $sender;
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin người gửi'
                ], 404);
            }
            
            if (!$receiver) {
                echo "receiver not found for UserID: " . $receiver;
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin người nhận'
                ], 404);
            }

            $messageData = MessageModel::create([
                'sender_id' => $user->UserID,
                'receiver_id' => $data['receiver_id'],
                'sender_role' => $user->Role,
                'content' => $data['content'],
                'is_read' => false,
                'sender_name' => $sender->Name,
                'sender_avatar' => $sender->ImgUrl,
                'receiver_name' => $receiver->Name,
                'receiver_avatar' => $receiver->ImgUrl,
            ]);

            // 2. Bắn sự kiện tới Reverb (Tương đương io.to().emit)
            broadcast(new MessageSent($messageData, $data['receiver_id']));

            return response()->json([
                'success' => true,
                'data' => $messageData
            ], 200);
        } catch (Exception $e) {
            echo "Error in sendMessage: " . $e->getMessage();
            
            return response()->json(['success' => false, 'message' => 'Lỗi server, không thể gửi tin nhắn'], 500);
        }
    }

    // THAY THẾ CHO socket.on('mark_as_read')
    public function markAsRead(Request $request)
    {
        try {
            $data = $request->validate([
                'otherUserId' => 'required|integer'
            ]);

            $user = $request->user();

            // 1. Cập nhật MongoDB (Dùng update() của Eloquent MongoDB)
            MessageModel::where('sender_id', $data['otherUserId'])
                ->where('receiver_id', $user->UserID)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // 2. Bắn sự kiện tới Reverb thông báo "Tôi đã đọc"
            broadcast(new MessagesMarkedAsRead($user->UserID, $data['otherUserId']));

            // 3. Trả về kết quả (Tương đương socket.emit('mark_as_read_success'))
            return response()->json([
                'success' => true,
                'data' => ['sender_id' => $data['otherUserId']]
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi khi đánh dấu tin nhắn'], 500);
        }
    }
    public function getConversations(Request $request)
    {
        try {
            $myId = (int) $request->user()->UserID;
            $conversations = $this->messageService->getConversations($myId);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu các hội thoại thành công',
                'data' => $conversations
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getChatHistory(Request $request, $otherUserId)
    {
        try {
            $myId = (int) $request->user()->UserID;
            $otherUserId = (int) $otherUserId;

            $messages = $this->messageService->getChatHistory($myId, $otherUserId);

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết cuộc trò chuyện thành công',
                'data' => $messages
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCountUnreadMessages(Request $request)
    {
        try {
            $myId = (int) $request->user()->UserID;
            $count = $this->messageService->getCountUnreadMessages($myId);

            return response()->json([
                'success' => true,
                'message' => 'Lấy số lượng tin nhắn chưa đọc thành công',
                'data' => $count
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
