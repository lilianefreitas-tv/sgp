<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTaskRequest extends StoreTaskRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $task = $this->route('task');

        $rules['parent_task_id'][] = Rule::notIn([$task?->id]);
        $rules['change_notes'] = ['nullable', 'string', 'max:1000'];

        return $rules;
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'change_notes' => 'observação da alteração',
        ];
    }
}
