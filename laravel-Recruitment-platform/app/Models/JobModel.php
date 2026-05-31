<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobModel extends Model
{
    protected $table = 'Jobs';
    protected $primaryKey = 'JobID';
    public $timestamps = false; // Tùy thuộc vào bạn có dùng created_at của Laravel không
    protected $guarded = [];
}