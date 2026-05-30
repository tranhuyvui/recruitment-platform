<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeModel extends Model
{
    protected $table = 'Resumes';

    protected $primaryKey = 'ResumeID';

    public $timestamps = false;

    protected $fillable = [
        'CandidateID',
        'Title',
        'ResumeFileUrl',
        'TemplateID',
        'Summary',
        'IsAnalyzed',
        'CreatedAt',
    ];
}