<?php

namespace App\Http\Requests;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\OrganizationContext;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateProjects() === true;
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'manager_id' => [
                'required',
                Rule::exists('organization_memberships', 'user_id')->where(fn ($query) => $query
                    ->where('organization_id', app(OrganizationContext::class)->id())
                    ->where('status', OrganizationMembershipStatus::Active->value)),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'objective' => ['required', 'string'],
            'justification' => ['nullable', 'string'],
            'execution_nature' => ['required', Rule::enum(ExecutionNature::class)],
            'financial_management_mode' => ['required', Rule::enum(FinancialManagementMode::class)],
            'management_level' => ['required', Rule::in(ManagementLevel::currentValues())],
            'methodology' => ['required', Rule::enum(ProjectMethodology::class)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente ou unidade demandante',
            'manager_id' => 'responsável principal',
            'execution_nature' => 'natureza da execução',
            'financial_management_mode' => 'tratamento financeiro',
            'management_level' => 'nível de gestão',
            'methodology' => 'metodologia',
            'start_date' => 'data de início',
            'expected_end_date' => 'previsão de entrega',
            'end_date' => 'data de encerramento',
        ];
    }
}
