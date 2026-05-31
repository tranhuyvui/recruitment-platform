<?php
// app/Http/Requests/UpdateJobRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'categoryId'          => 'sometimes|integer',
            'title'               => 'sometimes|string',
            'location'            => 'sometimes|string',
            'jobType'             => 'sometimes|string',
            'experienceRequired'  => 'sometimes|integer',
            'expiredDate'         => 'sometimes|date',
            'quantity'            => 'sometimes|integer',
            'salaryMin'           => 'sometimes|numeric',
            'salaryMax'           => 'sometimes|numeric',
            'description'         => 'sometimes|string',
            'requirements'        => 'sometimes|string',
            'workingSchedule'     => 'sometimes|string',
            'benefits'            => 'sometimes|array',
            'tags'                => 'sometimes|array',
            'interviewProcess'              => 'sometimes|array',
            'interviewProcess.*.roundOrder' => 'sometimes|integer',
            'interviewProcess.*.roundTitle' => 'sometimes|string',
            'interviewProcess.*.details'    => 'sometimes|string',
        ];
    }
}
