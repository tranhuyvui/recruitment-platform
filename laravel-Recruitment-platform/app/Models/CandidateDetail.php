<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; 

class CandidateDetail extends Model
{
    protected $connection = 'mongodb'; 
    
    protected $collection = 'candidate_details'; 

    protected $fillable = [
        'candidateId',
        'experience',
        'education',
        'projects',
    ];

}