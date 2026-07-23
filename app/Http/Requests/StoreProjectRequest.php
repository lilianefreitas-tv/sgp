<?php

namespace App\Http\Requests;

use App\Enums\ManagementLevel;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                'required',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'manager_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
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

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente ou unidade demandante',
            'manager_id' => 'responsável principal',
            'management_level' => 'nível de gestão',
            'start_date' => 'data de início',
            'expected_end_date' => 'previsão de entrega',
            'end_date' => 'data de encerramento',
        ];
    }
}
