<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'resumeId' => $this->route('resumeId'),
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
            'resume.create-manual' => $this->createManualResumeRules(),
            'resume.update-manual' => $this->updateManualResumeRules(),
            'resume.generate-summary' => $this->generateSummaryRules(),
            default => [],
        };
    }
    private function normalizeArrayField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
    
        if (is_string($value)) {
            $decoded = json_decode($value, true);
    
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
    
        return [];
    }
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->route()?->getName() !== 'resume.generate-summary') {
                return;
            }
    
            $skills = $this->normalizeArrayField($this->input('skills'));
            $experience = $this->normalizeArrayField($this->input('experience'));
            $education = $this->normalizeArrayField($this->input('education'));
    
            if (empty($skills) && empty($experience) && empty($education)) {
                $validator->errors()->add(
                    'resumeData',
                    'Vui lòng nhập ít nhất Kỹ năng, Kinh nghiệm hoặc Học vấn để AI có dữ liệu viết bài!'
                );
            }
        });
    }
    private function generateSummaryRules(): array
    {
        return [
            'targetTitle' => ['nullable', 'string'],
    
            'skills' => ['nullable'],
            'experience' => ['nullable'],
            'education' => ['nullable'],
            'projects' => ['nullable'],
        ];
    }

    private function createManualResumeRules(): array
    {
        return [
            'FullName' => ['nullable', 'string', 'min:1'],
            'Phone' => ['nullable', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'DateOfBirth' => ['nullable', 'date_format:Y-m-d'],
            'Address' => ['nullable', 'string'],

            'title' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'templateId' => ['nullable', 'integer', 'min:1'],

            'AvatarUrl' => ['nullable', 'file'],
            'ExistingAvatarUrl' => ['nullable', 'url'],

            'skills' => ['nullable'],
            'experience' => ['nullable'],
            'education' => ['nullable'],
            'projects' => ['nullable'],
        ];
    }

    private function updateManualResumeRules(): array
    {
        return [
            'resumeId' => ['required', 'integer', 'min:1'],

            'title' => ['required', 'string', 'min:10', 'max:255'],

            'templateId' => ['nullable', 'integer', 'min:1'],

            'summary' => ['nullable', 'string'],

            // Nếu FE gửi form-data thì các field này có thể là JSON string.
            // Service sẽ parse bằng normalizeArrayField().
            'skills' => ['nullable'],
            'experience' => ['nullable'],
            'education' => ['nullable'],
            'projects' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'resumeId.required' => 'resumeId là bắt buộc',
            'resumeId.integer' => 'resumeId phải là số nguyên',
            'resumeId.min' => 'resumeId phải là số nguyên dương',

            'title.required' => 'Vui lòng điền title!',
            'title.min' => 'Title phải từ 10 đến 255 ký tự!',
            'title.max' => 'Title phải từ 10 đến 255 ký tự!',

            'templateId.integer' => 'Mã giao diện (TemplateID) không hợp lệ, phải là số nguyên dương!',
            'templateId.min' => 'Mã giao diện (TemplateID) không hợp lệ, phải là số nguyên dương!',

            'FullName.string' => 'Họ và tên phải là chuỗi',
            'FullName.min' => 'Họ và tên không được để trống',

            'Phone.regex' => 'Số điện thoại không đúng định dạng (Việt Nam)',

            'DateOfBirth.date_format' => 'Ngày sinh phải theo định dạng hợp lệ (YYYY-MM-DD)',

            'Address.string' => 'Địa chỉ phải là chuỗi',

            'AvatarUrl.file' => 'Ảnh đại diện phải là file',
            'ExistingAvatarUrl.url' => 'ExistingAvatarUrl phải là URL hợp lệ',
        ];
    }
}