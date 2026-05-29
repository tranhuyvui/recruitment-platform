<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedJobModel extends Model
{
    protected $table = 'SavedJobs';

    public $timestamps = false;

    protected $fillable = [
        'CandidateID',
        'JobID',
        'SavedAt',
    ];
}