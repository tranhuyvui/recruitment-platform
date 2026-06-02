import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Khai báo kiểu cho biến global window để TypeScript không báo lỗi
declare global {
    interface Window {
        Pusher: any;
        Echo: any;
    }
}

export const setupEcho = (token: string) => {
    window.Pusher = Pusher;
    // const backendUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY, // Sẽ cấu hình trong file .env sau
        wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `https://recruitment-platform-production-462b.up.railway.app/broadcasting/auth`,
        auth: {
            headers: {
                Authorization: `Bearer ${token}`
            }
        }
    });
};

export const disconnectEcho = () => {
    if (window.Echo) {
        window.Echo.disconnect();
    }
};