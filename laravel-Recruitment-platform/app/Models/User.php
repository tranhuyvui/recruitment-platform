<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'Users';
    protected $primaryKey = 'UserID';

    // Tắt timestamps nếu bảng cũ của bạn không có cột created_at và updated_at
    public $timestamps = false;

    protected $fillable = [
        'Email',
        'PasswordHash',
        'Role',
        'Status',
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'userId' => $this->UserID,
            'role' => $this->Role
        ];
    }
}
