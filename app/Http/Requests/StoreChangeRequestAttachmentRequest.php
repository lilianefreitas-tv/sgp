<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $changeRequest = $this->route('changeRequest');

        return $this->user() !== null
            && $changeRequest !== null
            && $this->user()->can('manageAttachments', $changeRequest);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attachment_kind' => ['required', Rule::in(['attachment', 'evidence'])],
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
