<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
