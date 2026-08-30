<?php

namespace App\Http\Requests;

use App\Enums\TestExecutionResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $project !== null && $this->user()->canExecuteTests($project);
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(TestExecutionResult::class)],
            'environment' => ['required', 'string', 'max:200'],
            'observed_result' => ['required', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'defect_reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
