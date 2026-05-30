<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class JobSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
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
            'job-skills.list' => $this->getJobSkillsRules(),
            'job-skills.sync' => $this->syncJobSkillsRules(),
            'job-skills.remove' => $this->removeJobSkillRules(),
            default => [],
        };
    }

    private function getJobSkillsRules(): array
    {
        $this->merge(['jobId' => $this->route('jobId')]);
        
        return [
            'jobId' => ['required', 'integer', 'min:1'],
        ];
    }

    private function syncJobSkillsRules(): array
    {
        $this->merge(['jobId' => $this->route('jobId')]);
        
        return [
            'jobId'      => ['required', 'integer', 'min:1'],
            'skillIds'   => ['required', 'array'],
            'skillIds.*' => ['integer', 'min:1'], 
        ];
    }

    private function removeJobSkillRules(): array
    {
        $this->merge([
            'jobId'   => $this->route('jobId'),
            'skillId' => $this->route('skillId')
        ]);
        
        return [
            'jobId'   => ['required', 'integer', 'min:1'],
            'skillId' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'jobId.required'     => 'Thiếu ID của bài đăng trên URL.',
            'jobId.integer'      => 'ID bài đăng phải là số.',
            'jobId.min'          => 'ID bài đăng phải là số nguyên dương.',
            
            'skillId.required'   => 'Thiếu ID kỹ năng cần xóa trên URL.',
            'skillId.integer'    => 'ID kỹ năng phải là số.',
            'skillId.min'        => 'ID kỹ năng phải là số nguyên dương.',
            
            'skillIds.required'  => 'Vui lòng cung cấp danh sách kỹ năng.',
            'skillIds.array'     => 'Dữ liệu kỹ năng phải là một mảng (Array).',
            'skillIds.*.integer' => 'Từng kỹ năng trong mảng phải là định dạng số.',
            'skillIds.*.min'     => 'ID kỹ năng trong mảng phải là số nguyên dương.',
        ];
    }
}