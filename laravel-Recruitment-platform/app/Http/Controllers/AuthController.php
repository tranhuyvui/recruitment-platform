<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;   
use Exception;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            $data = $this->authService->login($credentials['email'], $credentials['password']);

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
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
    public function logout(Request $request)
    {
        try {
           
            \Illuminate\Support\Facades\Auth::logout();
            
            $refreshToken = $request->input('refreshToken');
            if ($refreshToken) {
                \Illuminate\Support\Facades\Redis::del("refreshToken:{$refreshToken}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Đăng xuất thành công'
            ], 200);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ], [
            'email.required' => 'Vui lòng nhập email',
            'email.email' => 'Email không đúng định dạng'
        ]);

        try {
            $result = $this->authService->requestOtp($request->email);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ], 200);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            // Đảm bảo mã lỗi HTTP hợp lệ
            $statusCode = ($statusCode >= 100 && $statusCode < 600) ? $statusCode : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
    public function verifyOtp(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string'
            ], [
                'email.required' => 'Vui lòng nhập email',
                'email.email' => 'Email không đúng định dạng',
                'otp.required' => 'Vui lòng nhập mã OTP',
            ]);
            $result = $this->authService->verifyOtp($data['email'], $data['otp']);

            return response()->json([
                'success' => true,
                'message' => 'Xác thực thành công',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            $statusCode = ($statusCode >= 100 && $statusCode < 600) ? $statusCode : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'verifyToken' => 'required|string',
                'password' => 'required|string|min:6',
                'role' => 'nullable|string'
            ], [
                'verifyToken.required' => 'Vui lòng cung cấp token xác thực',
                'password.required' => 'Vui lòng nhập mật khẩu',
                'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự'
            ]);
            $user = $this->authService->register(
                $data['verifyToken'],
                $data['password'],
                $data['role'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo tài khoản thành công',
                'data' => $user
            ], 201);
        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            $statusCode = ($statusCode >= 100 && $statusCode < 600) ? $statusCode : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
    public function refreshToken(Request $request)
    {
        try {
            $data = $request->validate([
                'refreshToken' => 'required|string'
            ], [
                'refreshToken.required' => 'Chưa cung cấp refresh token',
                'refreshToken.string' => 'Refresh token không hợp lệ'
            ]);

            $result = $this->authService->refreshToken($data['refreshToken']);

            return response()->json([
                'success' => true,
                'message' => 'Lấy accessToken thành công',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            $statusCode = ($statusCode >= 100 && $statusCode < 600) ? $statusCode : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
    public function getProfile(Request $request)
    {
        try {
            // $userId = auth()->userId;
            
            $userId = $request->user()->UserID;
            echo "UserID from token: {$request->user()}";
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $profileData = $this->authService->getProfile($userId);

            if (!$profileData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin hồ sơ'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin hồ sơ thành công',
                'data' => $profileData
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error getting profile: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi lấy thông tin hồ sơ'
            ], 500);
        }
    }
    public function requestOtpForgotPassword(Request $request)
    {
        try {
            $data = $request->validate(['email' => 'required|email']);
            $user = $this->authService->searchUserByEmail($data['email']);

            if (!$user || $user->Status === 'Banned') {
                throw new Exception('Email này không tồn tại trong hệ thống hoặc đã bị cấm', 404);
            }

            // Giả định bạn đã có hàm sendOtpMail trong AuthService
            $this->authService->requestOtp($user->Email);

            return response()->json([
                'success' => true,
                'message' => 'Mã OTP đã gửi vào Email của sếp!'
            ], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $data = $request->validated();

            $email = Redis::get("verifyToken:{$data['verifyToken']}");
            if (!$email) {
                throw new Exception('Phiên làm việc đã hết hạn. Sếp vui lòng xác thực lại OTP nhé!', 400);
            }

            $user = $this->authService->searchUserByEmail($email);
            if (!$user) throw new Exception('Email không tồn tại', 404);

            $this->authService->updatePassword($user->UserID, $data['newPassword']);
            Redis::del("verifyToken:{$data['verifyToken']}");

            return response()->json(['success' => true, 'message' => 'Đặt lại mật khẩu thành công!'], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }


    // --- BẢO MẬT TÀI KHOẢN (Cần Đăng Nhập) ---

    public function requestOtpAuth(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $user = $this->authService->searchUserById($userId);

            if (!$user) throw new Exception('Tài khoản không tồn tại', 404);

            $this->authService->requestOtp($user->Email);

            return response()->json([
                'success' => true,
                'message' => 'Mã OTP xác thực hành động đã được gửi'
            ], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $data = $request->validated();

            $user = $this->authService->searchUserById($userId);
            if (!$user) throw new Exception('Tài khoản không tồn tại', 404);

            // Kiểm tra mật khẩu cũ bằng Hash::check (tương đương bcrypt.compare)
            if (!Hash::check($data['oldPassword'], $user->PasswordHash)) {
                throw new Exception('Mật khẩu cũ không chính xác', 400);
            }

            $this->authService->updatePassword($userId, $data['newPassword']);

            return response()->json(['success' => true, 'message' => 'Đổi mật khẩu thành công'], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    public function deleteAccount(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $data = $request->validated();

            $user = $this->authService->searchUserById($userId);
            if (!$user) throw new Exception('Tài khoản không tồn tại', 404);

            // 1. Xác thực OTP
            $this->authService->verifyOtp($user->Email, $data['otp']);

            // 2. Xác thực Mật khẩu
            if (!Hash::check($data['password'], $user->PasswordHash)) {
                throw new Exception('Mật khẩu không đúng để xác nhận xóa', 400);
            }

            // 3. Xóa mềm (Update Status)
            $this->authService->updateUserStatus($userId, 'Deleted');

            return response()->json(['success' => true, 'message' => 'Tài khoản đã được xóa mềm thành công'], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    public function getCurrentRole(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $role = $this->authService->getCurrentRole($userId);

            return response()->json([
                'success' => true,
                'message' => 'Lấy Role thành công',
                'data' => $role
            ], 200);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    // Hàm phụ trợ để bắt lỗi JSON cho gọn file
    private function handleError(Exception $e)
    {
        \Illuminate\Support\Facades\Log::error($e->getMessage());
        $statusCode = $e->getCode() ?: 500;
        $statusCode = ($statusCode >= 100 && $statusCode < 600) ? $statusCode : 500;
        return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
    }
}