<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\DB;

use Exception;

class AuthService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('Email', $email)->first();
        if (!$user ) {
            throw new Exception('Tài khoản không tồn tại', 404);
        }
        if ($user->Status !== 'Active') {
            throw new Exception('Tài khoản đang bị khóa', 403);
        }

        $compatibleHash = Str::replaceFirst('$2b$', '$2y$', $user->PasswordHash);

        if (!Hash::check($password, $compatibleHash)) {
            throw new Exception('Mật khẩu không đúng', 401);
        }

        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = Str::random(64); 

        Redis::setex("refreshToken:{$refreshToken}", 7 * 24 * 60 * 60, $user->UserID);    

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'role' => $user->Role
        ];
    }
    public function requestOtp(string $email): array
    {
        $userExists = User::where('Email', $email)->exists();
        if ($userExists) {
            throw new Exception("Tài khoản đã tồn tại", 409);
        }

        $lockKey = "otp_lock:{$email}";
        if (Redis::exists($lockKey)) {
            $ttl = Redis::ttl($lockKey);
            throw new Exception("Vui lòng đợi {$ttl} giây trước khi gửi lại mã", 429); 
        }

        $otp = sprintf("%06d", mt_rand(1, 999999));
        $hashedOtp = hash('sha256', $otp);
        Redis::setex("otp:{$email}", 300, $hashedOtp);

        Redis::setex($lockKey, 60, 1);

        try {
            Mail::to($email)->queue(new SendOtpMail($otp));
        } catch (\Exception $e) {
            echo "Error sending OTP email: " . $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Error sending OTP email: ' . $e->getMessage());

            Redis::del($lockKey);

            throw new Exception('Lỗi hệ thống, không thể gửi email lúc này', 500);
        }

        return ['message' => 'Mã xác thực đã được gửi đến email của bạn'];
    }
    public function verifyOtp(string $email, string $otp): array
    {
        $hashedOtp = Redis::get("otp:{$email}");

        if (!$hashedOtp) {
            throw new Exception('OTP không tồn tại hoặc đã hết hạn', 400);
        }
        $hashedOtpInput = hash('sha256', $otp);

        if ($hashedOtp !== $hashedOtpInput) {
            throw new Exception('OTP không hợp lệ', 400);
        }
        $verifyToken = Str::random(64);

        Redis::setex("verifyToken:{$verifyToken}", 3600, $email);

        Redis::del("otp:{$email}");
        Redis::del("otp_lock:{$email}"); 
        return [
            'message' => 'OTP hợp lệ',
            'verifyToken' => $verifyToken
        ];
    }

    public function register(string $verifyToken, string $password, ?string $role): bool
    {
        $email = Redis::get("verifyToken:{$verifyToken}");

        if (!$email) {
            throw new Exception('Verify token không hợp lệ hoặc đã hết hạn', 400);
        }

        $hashedPassword = Hash::make($password);

        $user = User::create([
            'Email' => $email,
            'PasswordHash' => $hashedPassword,
            'Role' => $role ?? 'Candidate',
            'Status' => 'Active' 
        ]);
        Redis::del("verifyToken:{$verifyToken}");
        return (bool)$user;
    }
    public function refreshToken(string $refreshToken): array
    {
        $userId = Redis::get("refreshToken:{$refreshToken}");

        if (!$userId) {
            throw new Exception('Refresh token không hợp lệ hoặc đã hết hạn', 401);
        }
        $user = User::find($userId);

        if (!$user) {
            throw new Exception('Tài khoản không tồn tại', 404);
        }
        //xoas password hash để tránh bị lộ khi trả về client
        $user->PasswordHash = null;
        $user->Status = null;
        $user->CreatedAt = null;
        $user->Email = null;

        $newAccessToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
        return [
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60 
        ];
    }
    public function getProfile(int $userId)
    {
        $cacheKey = "profile:u{$userId}";
        $cachedProfile = Redis::get($cacheKey);

        if ($cachedProfile) {
            return json_decode($cachedProfile);
        }

        $rawProfile = $this->getProfileFromDb($userId);
        // echo "Cache miss for user profile: {$rawProfile}";

        if (!$rawProfile) {
            return null;
        }

        Redis::setex($cacheKey, 3600, json_encode($rawProfile));
        return $rawProfile;
    }

    private function getProfileFromDb(int $userId)
    {
        $candidateQuery = DB::table('Candidates as c')
            ->join('Users as u', 'c.CandidateID', '=', 'u.UserID')
            ->where('c.CandidateID', $userId)
            ->select('c.CandidateId as ProfileID', 'c.FullName as Name', 'c.AvatarUrl as ImgUrl', 'u.Email');

        $employerQuery = DB::table('Employers as e')
            ->join('Companies as comp', 'e.CompanyID', '=', 'comp.CompanyID')
            ->join('Users as u', 'e.EmployerID', '=', 'u.UserID')
            ->where('e.EmployerID', $userId)
            ->select('e.EmployerID as ProfileID', 'comp.CompanyName as Name', 'comp.LogoUrl as ImgUrl', 'u.Email');

        return $candidateQuery->union($employerQuery)->first();
    }
    public function searchUserByEmail(string $email)
    {
        return User::where('Email', $email)->first();
    }

    public function searchUserById(int $userId)
    {
        return User::find($userId);
    }

    public function updatePassword(int $userId, string $newPassword)
    {
        $hashedPassword = Hash::make($newPassword);
        return User::where('UserID', $userId)->update(['PasswordHash' => $hashedPassword]);
    }

    public function updateUserStatus(int $userId, string $status)
    {
        DB::transaction(function () use ($userId, $status) {

            DB::table('Users')
                ->where('UserID', $userId)
                ->update(['Status' => $status]);

            $employerStatus = ($status === 'Banned') ? 'Rejected' : 'Approved';
            DB::table('Employers')
                ->where('EmployerID', $userId)
                ->update(['ApprovalStatus' => $employerStatus]);
        });
    }

    public function getCurrentRole(int $userId)
    {
        $user = User::select('Role')->where('UserID', $userId)->first();
        return $user ? $user->Role : null;
    }
}