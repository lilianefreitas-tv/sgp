<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestUrgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user() !== null
            && $project !== null
            && $this->user()->canContributeToProject($project);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'origin' => ['required', Rule::enum(ChangeRequestOrigin::class)],
            'description' => ['nullable', 'string', 'max:10000'],
            'justification' => ['nullable', 'string', 'max:10000'],
            'urgency' => ['nullable', Rule::enum(ChangeRequestUrgency::class)],
            'baseline_id' => ['nullable', 'integer'],
            'requester_id' => ['nullable', 'integer'],
            'analyst_id' => ['nullable', 'integer'],
            'affected' => ['nullable', 'array'],
            'affected.requirement' => ['nullable', 'array', 'max:100'],
            'affected.requirement.*' => ['integer', 'distinct'],
            'affected.task' => ['nullable', 'array', 'max:100'],
            'affected.task.*' => ['integer', 'distinct'],
            'affected.artifact' => ['nullable', 'array', 'max:100'],
            'affected.artifact.*' => ['integer', 'distinct'],
            'affected.contract' => ['nullable', 'array', 'max:100'],
            'affected.contract.*' => ['integer', 'distinct'],
            'affected.document' => ['nullable', 'array', 'max:100'],
            'affected.document.*' => ['integer', 'distinct'],
        ];
    }
}
