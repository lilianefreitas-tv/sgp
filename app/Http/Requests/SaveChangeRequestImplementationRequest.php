<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestBaselineDisposition;
use App\Enums\ChangeRequestContractDisposition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChangeRequestImplementationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $changeRequest = $this->route('changeRequest');

        return $this->user() !== null
            && $changeRequest !== null
            && $this->user()->can('updateImplementation', $changeRequest);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'responsible_id' => ['nullable', 'integer'],
            'plan_summary' => ['nullable', 'string', 'max:10000'],
            'execution_summary' => ['nullable', 'string', 'max:10000'],
            'verification_summary' => ['nullable', 'string', 'max:10000'],
            'planned_start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'contract_disposition' => ['required', Rule::enum(ChangeRequestContractDisposition::class)],
            'contract_id' => [
                'nullable',
                'integer',
                Rule::exists('project_contracts', 'id')
                    ->where('project_id', $project?->id)
                    ->where('organization_id', $project?->organization_id),
            ],
            'contract_justification' => ['nullable', 'string', 'max:10000'],
            'amendment_reference' => ['nullable', 'string', 'max:160'],
            'amendment_summary' => ['nullable', 'string', 'max:10000'],
            'amendment_effective_date' => ['nullable', 'date'],
            'baseline_disposition' => ['required', Rule::enum(ChangeRequestBaselineDisposition::class)],
            'baseline_title' => ['nullable', 'string', 'max:160'],
            'baseline_justification' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
