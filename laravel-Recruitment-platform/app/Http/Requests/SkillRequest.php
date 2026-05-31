<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SkillRequest extends FormRequest
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
            'skills.detail', 'skills.delete' => $this->idRules(),
            'skills.create' => $this->createRules(),
            'skills.update' => $this->updateRules(),
            default => [],
        };
    }

    private function idRules(): array
    {
        $this->merge(['id' => $this->route('id')]);
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function createRules(): array
    {
        return [
            'skillName' => ['required', 'string', 'max:100'],
        ];
    }

    private function updateRules(): array
    {
        $this->merge(['id' => $this->route('id')]);
        return [
            'id' => ['required', 'integer', 'min:1'],
            'skillName' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Thiếu ID kỹ năng trên URL',
            'id.integer' => 'ID kỹ năng phải là số',
            'id.min' => 'ID kỹ năng phải là số nguyên dương',
            'skillName.required' => 'Tên kỹ năng không được để trống',
            'skillName.max' => 'Tên kỹ năng quá dài, tối đa 100 ký tự thôi sếp ơi!',
        ];
    }
}