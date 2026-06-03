import { defineStore } from "pinia";
import { getChatHistory, getConversations, getCountUnreadMessages, markAsReadApi, sendMessageApi } from "../services/message";
import { ref } from "vue";
import { useAuthStore } from "./auth";
// import { echo } from "../services/echo";

export const useMessageStore = defineStore('message', () => {
    const authStore = useAuthStore();

    const conversations = ref<any[]>([]);
    const chatHistory = ref<any[]>([])
    const loading = ref<boolean>(false);
    const error = ref<string>('');

    const isEchoInitialized = ref(false);

    const fetchConversations = async () => {
        try {
            loading.value = true;
            error.value = '';
            const response = await getConversations();
            conversations.value = response.data || [];
        } catch (err: any) {
            console.error("Lỗi khi lấy danh sách cuộc trò chuyện:", err.response?.data);
            error.value = err.response?.data?.message || 'Đã xảy ra lỗi khi lấy danh sách cuộc trò chuyện';
        } finally {
            loading.value = false;
        }
    };
    const fetchChatHistory = async (otherUserId: number) => {
        try {
            loading.value = true;
            error.value = '';
            const response = await getChatHistory(otherUserId);
            chatHistory.value = response.data || [];
        } catch (err: any) {
            console.error("Lỗi khi lấy lịch sử trò chuyện:", err.response?.data);
            error.value = err.response?.data?.message || 'Đã xảy ra lỗi khi lấy lịch sử trò chuyện';
        } finally {
            loading.value = false;
        }
    };
    // const initSocketListeners = () => {
    //     socket.off('receive_message');
    //     socket.off('message_sent_success');
    //     socket.off('message_error');
    //     socket.off('messages_marked_as_read');
    //     socket.off('mark_as_read_success');

    //     socket.on('receive_message', (messageData: any) => {
    //         chatHistory.value.push(messageData);
    //         fetchConversations();
    //         fetchUnreadCount();
    //     });
    //     socket.on('message_sent_success', (messageData: any) => {
    //         chatHistory.value.push(messageData);
    //         fetchConversations();
    //     });

    //     socket.on('message_error', (err: any) => {
    //         console.error("Lỗi gửi tin:", err.message);
    //         error.value = err.message || 'Không thể gửi tin nhắn';
    //     });

    //     socket.on('messages_marked_as_read', ({ readerId }) => {
    //         chatHistory.value.forEach(msg => {
    //             if (msg.sender_id !== readerId && !msg.is_read) {
    //                 msg.is_read = true;
    //             }
    //         });

    //         fetchConversations();
    //     });
    //     socket.on('mark_as_read_success', () => {
    //         fetchConversations();
    //         fetchUnreadCount();
    //     });
    // };
    // const markAsRead = (otherUserId: number) => {
    //     if (!socket.connected) return;
    //     socket.emit('mark_as_read', otherUserId);

    //     chatHistory.value.forEach(msg => {
    //         if (msg.sender_id === otherUserId && !msg.is_read) {
    //             msg.is_read = true;
    //         }
    //     });
    // };
    // const sendMessage = async (receiverId: number, content: string) => {
    //     if (!socket.connected) {
    //         error.value = 'Chưa kết nối đến máy chủ chat.';
    //         return;
    //     }

    //     socket.emit('send_message', {
    //         receiver_id: receiverId,
    //         content: content
    //     });
    // };
    const unreadCount = ref<number>(0);

    const fetchUnreadCount = async () => {
        try {
            const res = await getCountUnreadMessages();
            unreadCount.value = res.data;
        } catch (err) {
            console.error(err);
        }
    }
    const initEchoListeners = (myUserId: number) => {
        if (!window.Echo || isEchoInitialized.value) return;

        // Dọn dẹp listener cũ (nếu có) để tránh nghe trùng lặp khi chuyển trang
        window.Echo.leave(`user.${myUserId}`);

        // Đăng ký nghe trên kênh của mình
        window.Echo.private(`user.${myUserId}`)
            .listen('.receive_message', (event: any) => {
                // Nhận tin nhắn mới từ người khác
                chatHistory.value.push(event.messageData);
                fetchConversations();
                fetchUnreadCount();
            })
            .listen('.messages_marked_as_read', () => {
                // Người khác đã đọc tin nhắn của mình
                chatHistory.value.forEach(msg => {
                    // Update UI các dòng "Đã gửi" thành "Đã xem"
                    if (msg.sender_id === myUserId && !msg.is_read) {
                        msg.is_read = true;
                    }
                });
                fetchConversations();
            });
        isEchoInitialized.value = true;
    };

    const sendMessage = async (receiverId: number, content: string) => {
        const tempId = 'temp_' + Date.now();

        const tempMessage = {
            _id: tempId,
            sender_id: authStore.user?.ProfileID,
            receiver_id: receiverId,
            content: content,
            is_read: false,
            created_at: new Date().toISOString()
        };

        chatHistory.value.push(tempMessage);
        try {
            const response = await sendMessageApi(receiverId, content);

            const savedMessage = response.data.data;
            const index = chatHistory.value.findIndex(m => m._id === tempId);
            if (index !== -1) {
                chatHistory.value[index] = savedMessage;
            }

            fetchConversations();
        } catch (err: any) {
            console.error("Lỗi gửi tin:", err);
            error.value = 'Không thể gửi tin nhắn';
        }
    };

    const markAsRead = async (otherUserId: number) => {
        try {
            chatHistory.value.forEach(msg => {
                if (msg.sender_id === otherUserId && !msg.is_read) {
                    msg.is_read = true;
                }
            });
            await markAsReadApi(otherUserId);

            fetchConversations();
            fetchUnreadCount();
        } catch (err) {
            console.error("Lỗi khi đánh dấu đã đọc", err);
        }
    };
    return {
        conversations,
        loading,
        error,
        fetchConversations,
        fetchChatHistory,
        chatHistory,
        unreadCount,
        fetchUnreadCount,
        initEchoListeners,
        sendMessage,
        markAsRead,
        isEchoInitialized
    }
});