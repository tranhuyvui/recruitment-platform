<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as MongoModel;

class JobDetailModel extends MongoModel
{
    protected $connection = 'mongodb';
    protected $collection = 'job_details';
    protected $table = 'jobdetails';
    protected $guarded = [];
}