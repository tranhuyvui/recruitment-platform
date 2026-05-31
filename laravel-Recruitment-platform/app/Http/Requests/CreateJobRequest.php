<?php
// app/Http/Requests/CreateJobRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateJobRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'categoryId'          => 'required|integer',
            'title'               => 'required|string',
            'location'            => 'required|string',
            'jobType'             => 'required|string',
            'experienceRequired'  => 'required|integer',
            'expiredDate'         => 'required|date',
            'description'         => 'required|string',
            'requirements'        => 'required|string',
            'benefits'            => 'sometimes|array',
            'tags'                => 'sometimes|array',
            'interviewProcess'              => 'sometimes|array',
            'interviewProcess.*.roundOrder' => 'sometimes|integer',
            'interviewProcess.*.roundTitle' => 'sometimes|string',
            'interviewProcess.*.details'    => 'sometimes|string',
        ];
    }
}
