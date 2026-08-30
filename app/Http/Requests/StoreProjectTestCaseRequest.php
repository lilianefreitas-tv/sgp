<?php

namespace App\Http\Requests;

use App\Enums\TestCaseSeverity;
use App\Enums\TestCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $project !== null && $this->user()->canPlanTests($project);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'objective' => ['required', 'string', 'max:10000'],
            'preconditions' => ['nullable', 'string', 'max:10000'],
            'test_data' => ['nullable', 'string', 'max:10000'],
            'steps' => ['required', 'string', 'max:30000'],
            'expected_result' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::enum(TestCaseSeverity::class)],
            'status' => ['required', Rule::enum(TestCaseStatus::class)],
            'assigned_tester_id' => ['nullable', 'integer'],
            'requirement_id' => ['nullable', 'integer'],
            'change_request_id' => ['nullable', 'integer'],
            'baseline_id' => ['nullable', 'integer'],
        ];
    }
}
