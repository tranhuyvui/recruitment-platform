<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'CompanyID' => $this->route('companyID') ?? $this->route('CompanyID')
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
            'companies.request' => $this->requestCompanyRules(),

            'companies.create' => $this->createCompanyRules(),

            'companies.update-status' => $this->updateCompanyStatusRules(),

            'companies.update' => $this->updateCompanyRules(),

            'companies.detail',
            'admin.companies.detail' => $this->companyIdRules(),

            default => [],
        };
    }

    private function companyIdRules(): array
    {
        return [
            'CompanyID' => ['required', 'integer', 'min:1'],
        ];
    }

    private function requestCompanyRules(): array
    {
        return [
            'CompanyID' => ['required', 'integer', 'min:1'],
            'EmployerID' => ['required', 'integer', 'min:1'],
            'Position' => ['required', 'string'],
        ];
    }

    private function createCompanyRules(): array
    {
        return [
            'CompanyName' => ['required', 'string'],
            'TaxCode' => ['required', 'regex:/^\d{10}$/'],
            'Industry' => ['required', 'string'],

            'CompanyDescription' => ['nullable', 'string', 'min:10'],
            'Website' => ['nullable', 'url'],
            'ContactEmail' => ['nullable', 'email'],

            'City' => ['required', 'string'],

            'BusinessLicenseUrl' => ['required', 'file'],
            'LogoUrl' => ['nullable', 'file'],

            'Position' => ['required', 'string'],

        ];
    }

    private function updateCompanyStatusRules(): array
    {
        return [
            'CompanyID' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Pending,Approved,Rejected,Banned'],
        ];
    }

    private function updateCompanyRules(): array
    {
        return [
            'CompanyID' => ['required', 'integer', 'min:1'],

            'CompanyName' => ['nullable', 'string'],
            'CompanyDescription' => ['nullable', 'string'],

            'TaxCode' => ['nullable', 'regex:/^\d{10}$/'],
            'Industry' => ['nullable', 'string'],

            'Website' => ['nullable', 'url'],
            'ContactEmail' => ['nullable', 'email'],

            'City' => ['nullable', 'string'],

            'BusinessLicenseUrl' => ['nullable', 'file'],
            'LogoUrl' => ['nullable', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'CompanyID.required' => 'CompanyID là bắt buộc',
            'CompanyID.integer' => 'CompanyID phải là số nguyên',
            'CompanyID.min' => 'CompanyID phải là số nguyên dương',

            'EmployerID.required' => 'EmployerID là bắt buộc',
            'EmployerID.integer' => 'EmployerID phải là số nguyên',
            'EmployerID.min' => 'EmployerID phải là số nguyên dương',

            'CompanyName.required' => 'Tên công ty không được để trống',
            'CompanyName.string' => 'Tên công ty phải là chuỗi',

            'TaxCode.required' => 'TaxCode không được để trống',
            'TaxCode.regex' => 'TaxCode phải 10 chữ số',

            'Industry.required' => 'Ngành nghề là bắt buộc',
            'Industry.string' => 'Ngành nghề phải là chuỗi',

            'CompanyDescription.min' => 'Mô tả phải từ 10 kí tự trở lên',

            'Website.url' => 'Website phải là một URL hợp lệ',

            'ContactEmail.email' => 'Email không đúng định dạng',

            'City.required' => 'Thành phố không được để trống',
            'City.string' => 'Thành phố phải là chuỗi',

            'BusinessLicenseUrl.required' => 'Vui lòng upload ảnh Giấy phép kinh doanh',
            'BusinessLicenseUrl.file' => 'Giấy phép kinh doanh phải là file',

            'LogoUrl.file' => 'Logo phải là file',

            'Position.required' => 'Vị trí không được để trống',
            'Position.string' => 'Vị trí phải là chuỗi',

            'status.required' => 'status là bắt buộc',
            'status.in' => 'giá trị status không hợp lệ',
        ];
    }
}