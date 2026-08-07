<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKanbanColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project !== null && (
            $this->user()?->administersCurrentOrganization()
            || $this->user()?->hasProjectRole(ProjectRole::ProjectManager, $project)
        );
    }

    public function rules(): array
    {
        return [
            'columns' => ['required', 'array', 'size:'.count(TaskStatus::cases())],
            'columns.*.status' => ['required', 'distinct', Rule::enum(TaskStatus::class)],
            'columns.*.name' => ['required', 'string', 'max:100'],
            'columns.*.position' => [
                'required',
                'integer',
                'between:1,'.count(TaskStatus::cases()),
                'distinct',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'columns.*.name' => 'nome da coluna',
            'columns.*.position' => 'ordem da coluna',
        ];
    }
}
