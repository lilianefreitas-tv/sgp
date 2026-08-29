<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $project !== null && $this->user()->canExecuteTests($project);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,txt,csv,png,jpg,jpeg,webp,zip'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
