<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MessageModel extends Model
{
    // 1. Chỉ định kết nối tới MongoDB (Vì mặc định hệ thống đang là MySQL)
    protected $connection = 'mongodb';

    // 2. Tên Collection (Tương đương tên bảng)
    protected $collection = 'messages';

    // 3. Khai báo các cột ĐƯỢC PHÉP insert dữ liệu (Bảo mật Mass Assignment)
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'sender_role',
        'content',
        'is_read',
        'sender_name',
        'sender_avatar',
        'receiver_name',
        'receiver_avatar'
    ];

    // 4. Giá trị mặc định (Tương đương is_read: { default: false })
    protected $attributes = [
        'is_read' => false,
    ];

    // 5. Ép kiểu dữ liệu (Tương đương type: Number, type: Boolean bên Mongoose)
    // Giúp khi lấy ra từ DB, Laravel tự động biến nó thành đúng kiểu dữ liệu bạn cần
    protected $casts = [
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'is_read' => 'boolean',
    ];

    // (Tùy chọn) Laravel MẶC ĐỊNH đã bật timestamps (created_at, updated_at). 
    // Nên bạn không cần cấu hình { timestamps: true } như Mongoose nữa.
}