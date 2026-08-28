<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestClassification;
use App\Enums\ChangeRequestRecommendation;
use App\Enums\ChangeRequestRiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChangeRequestImpactAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        $changeRequest = $this->route('changeRequest');

        return $this->user() !== null
            && $changeRequest !== null
            && $this->user()->can('analyzeImpact', $changeRequest);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:10000'];

        return [
            'classification' => ['nullable', Rule::enum(ChangeRequestClassification::class)],
            'risk_level' => ['nullable', Rule::enum(ChangeRequestRiskLevel::class)],
            'recommendation' => ['nullable', Rule::enum(ChangeRequestRecommendation::class)],
            'executive_summary' => $text,
            'scope_impact' => $text,
            'requirements_impact' => $text,
            'technical_impact' => $text,
            'data_impact' => $text,
            'security_impact' => $text,
            'schedule_impact' => $text,
            'resources_impact' => $text,
            'cost_impact' => $text,
            'contract_impact' => $text,
            'quality_impact' => $text,
            'testing_impact' => $text,
            'operations_impact' => $text,
            'documentation_impact' => $text,
            'risks_and_mitigations' => $text,
            'estimated_effort_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_schedule_days' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'estimated_cost_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }
}
