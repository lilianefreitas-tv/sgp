<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveKanbanTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project !== null && (
            $this->user()?->isAdministrator()
            || $this->user()?->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $this->user()?->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)
            || $this->user()?->hasProjectRole(ProjectRole::Developer, $project)
        );
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'coluna de destino',
        ];
    }
}
