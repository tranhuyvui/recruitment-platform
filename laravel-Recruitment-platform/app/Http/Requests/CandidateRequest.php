<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CandidateRequest extends FormRequest
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
            'candidates.profile.upsert' => $this->upsertProfileRules(),
            'candidates.profile.detail' => $this->updateMasterProfileRules(),
            'candidates.skills.analyze' => $this->analyzeSkillsTextRules(),
            'candidates.skills.save' => $this->saveAnalyzedSkillsRules(),
            'employer.candidates.list' => $this->getCandidatesListRules(),
            'employer.candidates.detail' => $this->getCandidateDetailRules(),
            default => [],
        };
    }

    private function upsertProfileRules(): array
    {
        return [
            'FullName' => ['nullable', 'string', 'min:1'],
            'Phone' => ['nullable', 'string', 'regex:/(84|0[3|5|7|8|9])+([0-9]{8})\b/'], // Regex sdt VN
            'DateOfBirth' => ['nullable', 'date_format:Y-m-d'],
            'Address' => ['nullable', 'string'],
            'AvatarUrl' => ['nullable', 'file', 'image'],
        ];
    }

    private function updateMasterProfileRules(): array
    {
        return [
            'experience' => ['nullable', 'array'],
            'education' => ['nullable', 'array'],
            'projects' => ['nullable', 'array'],
        ];
    }

    private function analyzeSkillsTextRules(): array
    {
        return [
            'rawText' => ['required', 'string', 'min:10'],
        ];
    }

    private function saveAnalyzedSkillsRules(): array
    {
        return [
            'skills' => ['required', 'array', 'min:1'],
            'skills.*.skillName' => ['required', 'string'],
            'skills.*.isNew' => ['required', 'boolean'],
        ];
    }

    private function getCandidatesListRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function getCandidateDetailRules(): array
    {
        $this->merge(['id' => $this->route('id')]);
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'rawText.min' => 'Mô tả hơi ngắn, vui lòng nhập ít nhất 10 ký tự để AI phân tích chuẩn xác hơn',
            'skills.required' => 'Dữ liệu kỹ năng là bắt buộc',
            'id.required' => 'Sếp chưa truyền ID ứng viên trên URL kìa!',
        ];
    }
}