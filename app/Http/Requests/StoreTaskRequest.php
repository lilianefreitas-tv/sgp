<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project !== null && (
            $this->user()?->administersCurrentOrganization()
            || $this->user()?->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $this->user()?->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)
            || $this->user()?->hasProjectRole(ProjectRole::Developer, $project)
        );
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'responsible_id' => [
                'nullable',
                Rule::exists('project_user', 'user_id')->where(fn ($query) => $query
                    ->where('project_id', $project->id)
                    ->where('is_active', true)),
            ],
            'requirement_id' => [
                'nullable',
                Rule::exists('requirements', 'id')->where(fn ($query) => $query
                    ->where('project_id', $project->id)
                    ->where('is_active', true)),
            ],
            'parent_task_id' => [
                'nullable',
                Rule::exists('tasks', 'id')->where(fn ($query) => $query
                    ->where('project_id', $project->id)
                    ->whereNull('parent_task_id')
                    ->where('is_active', true)),
            ],
            'estimated_duration' => [
                'nullable',
                'regex:/^[0-9]{2,6}:[0-5][0-9]$/',
                'not_in:00:00',
            ],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'responsible_id' => 'responsável',
            'requirement_id' => 'requisito vinculado',
            'parent_task_id' => 'tarefa principal',
            'estimated_duration' => 'estimativa',
            'start_date' => 'data de início',
            'due_date' => 'prazo previsto',
            'is_active' => 'situação',
        ];
    }

    public function messages(): array
    {
        return [
            'estimated_duration.regex' => 'A estimativa deve estar no formato HH:MM, como 08:00, 01:30 ou 00:15.',
            'estimated_duration.not_in' => 'A estimativa deve ser maior que 00:00.',
        ];
    }
}
