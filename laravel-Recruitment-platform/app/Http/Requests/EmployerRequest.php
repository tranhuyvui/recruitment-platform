<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmployerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'EmployerID' => $this->route('EmployerID') ?? $this->route('employerID'),
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
            'employer.update-status' => $this->updateStatusRules(),

            default => [],
        };
    }

    private function updateStatusRules(): array
    {
        return [
            'EmployerID' => ['required', 'integer', 'min:1'],
            'ApprovalStatus' => ['required', 'in:Pending,Approved,Rejected'],
        ];
    }

    public function messages(): array
    {
        return [
            'EmployerID.required' => 'EmployerID là bắt buộc',
            'EmployerID.integer' => 'EmployerID phải là số nguyên',
            'EmployerID.min' => 'EmployerID phải là số nguyên dương',

            'ApprovalStatus.required' => 'Vui lòng truyền trạng thái',
            'ApprovalStatus.in' => 'ApprovalStatus không hợp lệ',
        ];
    }
}