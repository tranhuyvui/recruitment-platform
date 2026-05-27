<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerModel extends Model
{
    protected $table = 'Employers';

    protected $primaryKey = 'EmployerID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'EmployerID',
        'CompanyID',
        'Position',
        'ApprovalStatus',
    ];
}