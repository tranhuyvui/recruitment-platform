<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class JobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'JobID' => $this->route('JobID') ?? $this->route('jobID') ?? $this->input('JobID'),
            'ApplicationID' => $this->route('ApplicationID') ?? $this->route('applicationID'),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors()
        ], 422));
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'job-application.apply' => $this->applyJobRules(),

            'job-application.list-by-job' => $this->jobIdRules(),

            'job-application.detail' => $this->applicationIdRules(),
            
            'job-application.update-status' => $this->updateStatusRules(),

            default => [],
        };
    }

    private function applyJobRules(): array
    {
        return [
            'JobID' => ['required', 'integer', 'min:1'],
            'ResumeID' => ['required'],
        ];
    }

    private function jobIdRules(): array
    {
        return [
            'JobID' => ['required', 'integer', 'min:1'],
        ];
    }

    private function applicationIdRules(): array
    {
        return [
            'ApplicationID' => ['required', 'integer', 'min:1'],
        ];
    }
    private function updateStatusRules(): array
    {
        return [
            'ApplicationID' => ['required', 'integer', 'min:1'],
            'Status' => ['required', 'in:Pending,Reviewed,Accepted,Rejected,Cancelled'],
        ];
    }
    public function messages(): array
    {
        return [
            'JobID.required' => 'JobID là bắt buộc',
            'JobID.integer' => 'JobID phải là số nguyên',
            'JobID.min' => 'JobID phải là số nguyên dương',

            'ResumeID.required' => 'ResumeID là bắt buộc',

            'ApplicationID.required' => 'ApplicationID là bắt buộc',
            'ApplicationID.integer' => 'ApplicationID phải là số nguyên',
            'ApplicationID.min' => 'ApplicationID phải là số nguyên dương',
            'Status.required' => 'Vui lòng truyền trạng thái',
            'Status.in' => 'Status không hợp lệ',
        ];
    }
}