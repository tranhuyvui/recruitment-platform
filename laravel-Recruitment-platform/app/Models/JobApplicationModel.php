<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplicationModel extends Model
{
    protected $table = 'JobApplications';

    protected $primaryKey = 'ApplicationID';

    public $timestamps = false;

    protected $fillable = [
        'JobID',
        'CandidateID',
        'ResumeID',
        'Status',
        'MatchScore',
        'AI_Sumary_Review',
        'CreatedAt',
    ];
}