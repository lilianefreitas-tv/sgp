<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'context' => ['required', 'string', 'max:80', 'regex:/^(project|requirement|task):[0-9]+$/'],
            'description' => ['nullable', 'string', 'max:300'],
            'file' => [
                'required',
                'file',
                'max:'.config('sgp.attachments.max_kb'),
                'mimes:'.implode(',', config('sgp.attachments.allowed_extensions')),
            ],
        ];
    }
}
