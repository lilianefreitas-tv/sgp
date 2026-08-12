<?php

namespace App\Http\Requests;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()?->canManageProject($project) === true;
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $project->client_id)
                ),
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
}
