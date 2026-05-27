<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModel extends Model
{
    protected $table = 'Companies';

    protected $primaryKey = 'CompanyID';

    public $timestamps = false;

    protected $fillable = [
        'CompanyName',
        'CompanyDescription',
        'TaxCode',
        'Industry',
        'Website',
        'LogoUrl',
        'ContactEmail',
        'City',
        'Position',
        'BusinessLicenseUrl',
        'Status',
        'CreatedBy',
        'CreatedAt',
        'UpdatedAt',
    ];
}