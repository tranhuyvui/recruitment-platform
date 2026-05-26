<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Exception;

class AuthService
{
    public function login(string $email, string $password): array
    {
        // 1. Tìm user theo email (Sử dụng Eloquent thay vì raw SQL)
        // Lưu ý: Tên cột ('Email', 'Status', 'PasswordHash') viết hoa chữ cái đầu cho khớp với code Node.js của bạn
        $user = User::where('Email', $email)->first();

        // 2. Kiểm tra user tồn tại và trạng thái
        if (!$user || $user->Status === 'Banned') {
            throw new Exception('Tài khoản không tồn tại hoặc đã bị cấm', 404);
        }

        $compatibleHash = Str::replaceFirst('$2b$', '$2y$', $user->PasswordHash);

        if (!Hash::check($password, $compatibleHash)) {
            throw new Exception('Mật khẩu không đúng', 401);
        }

        // 4. Tạo token (Giả sử bạn có hàm generateToken riêng như trong Node.js)
        $accessToken = $this->generateToken($user->UserID, $user->Role, 'accessToken');
        $refreshToken = $this->generateToken($user->UserID, $user->Role, 'refreshToken');

        // 5. Lưu refreshToken vào Redis với thời gian sống là 7 ngày (7 * 24 * 60 * 60 = 604800 giây)
        // Redis::setex("refreshToken:{$refreshToken}", 604800, $user->UserID);

        // 6. Trả về data cho Controller
        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'role' => $user->Role
        ];
    }

    /**
     * Hàm giả lập tạo JWT token (Bên Laravel bạn nên dùng Tymon JWT-Auth hoặc Laravel Sanctum)
     */
    private function generateToken($userId, $role, $type): string
    {
        // Viết logic tạo token của bạn ở đây.
        // Đây chỉ là một chuỗi random ví dụ để API không bị lỗi.
        return base64_encode(json_encode([
            'userId' => $userId,
            'role' => $role,
            'type' => $type,
            'exp' => time() + ($type === 'accessToken' ? 3600 : 604800)
        ]));
    }
}