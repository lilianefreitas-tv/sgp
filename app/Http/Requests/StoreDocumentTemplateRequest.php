<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'version' => ['required', 'integer', 'min:1', 'max:999'],
            'header_text' => ['nullable', 'string', 'max:180'],
            'footer_text' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
