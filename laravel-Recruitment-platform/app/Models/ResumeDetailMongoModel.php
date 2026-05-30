<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ResumeDetailMongoModel extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'resumedetails';

    protected $fillable = [
        'resumeId',
        'templateId',
        'title',
        'summary',
        'AvatarUrl',
        'skills',
        'experience',
        'education',
        'projects',
    ];
}