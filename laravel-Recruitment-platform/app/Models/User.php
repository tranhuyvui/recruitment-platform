<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // 1. Chỉ định chính xác tên bảng có chữ U viết hoa
    protected $table = 'Users';

    // 2. Chỉ định lại khóa chính (vì Node.js bạn dùng UserID)
    protected $primaryKey = 'UserID';

    // 3. Tắt timestamps nếu bảng cũ của bạn không có cột created_at và updated_at
    public $timestamps = false;

    // Điền các cột cho phép bóc tách dữ liệu vào đây (nếu cần)
    protected $fillable = [
        'Email',
        'PasswordHash',
        'Role',
        'Status',
    ];
}