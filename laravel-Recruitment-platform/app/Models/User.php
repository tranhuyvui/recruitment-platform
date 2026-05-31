<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Cache;

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
    public static function find($id, $columns = ['*'])
    {
        logger("User::find called with id: {$id}");
        return Cache::remember("jwt_user:{$id}", 300, function () use ($id, $columns) {
            return parent::find($id, $columns);
        });
    }
}
