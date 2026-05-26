<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Exception;

class AuthController extends Controller
{
    protected AuthService $authService;

    // Inject Service vào Controller
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Lấy email, password từ Request đã được validate
            $credentials = $request->validated();

            // Chuyển việc xử lý cho Service
            $data = $this->authService->login($credentials['email'], $credentials['password']);

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            // Bắt lỗi và trả về đúng HTTP status code
            $statusCode = $e->getCode();
            // Fallback về 500 nếu mã lỗi không phải là HTTP status hợp lệ
            if (!is_numeric($statusCode) || $statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}