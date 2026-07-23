<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project !== null && (
            $this->user()?->isAdministrator()
            || $this->user()?->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $this->user()?->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)
        );
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(RequirementType::class)],
            'priority' => ['required', Rule::enum(RequirementPriority::class)],
            'status' => ['required', Rule::enum(RequirementStatus::class)],
            'acceptance_criteria' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:150'],
            'responsible_id' => [
                'nullable',
                Rule::exists('project_user', 'user_id')->where(fn ($query) => $query
                    ->where('project_id', $project->id)
                    ->where('is_active', true)),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'acceptance_criteria' => 'critérios de aceite',
            'responsible_id' => 'responsável',
            'source' => 'origem da demanda',
            'is_active' => 'situação',
        ];
    }
}
