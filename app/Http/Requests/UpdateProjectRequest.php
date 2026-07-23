<?php

namespace App\Http\Requests;

use App\Enums\ManagementLevel;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()?->isAdministrator() === true
            || $this->user()?->hasProjectRole(ProjectRole::ProjectManager, $project) === true;
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $project->client_id)
                ),
            ],
            'manager_id' => [
                'required',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $project->manager_id)
                ),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'objective' => ['required', 'string'],
            'justification' => ['nullable', 'string'],
            'management_level' => ['required', Rule::enum(ManagementLevel::class)],
            'methodology' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
