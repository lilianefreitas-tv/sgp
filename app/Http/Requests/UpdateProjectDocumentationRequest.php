<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_context' => ['required', 'string', 'max:10000'],
            'problem_statement' => ['required', 'string', 'max:10000'],
            'solution_summary' => ['required', 'string', 'max:10000'],
            'target_audience' => ['required', 'string', 'max:6000'],
            'scope_included' => ['required', 'string', 'max:10000'],
            'scope_excluded' => ['nullable', 'string', 'max:10000'],
            'assumptions' => ['nullable', 'string', 'max:10000'],
            'constraints' => ['nullable', 'string', 'max:10000'],
            'success_criteria' => ['nullable', 'string', 'max:10000'],
            'future_vision' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_context' => 'contexto',
            'problem_statement' => 'problema',
            'solution_summary' => 'solução proposta',
            'target_audience' => 'público-alvo',
            'scope_included' => 'escopo incluído',
            'scope_excluded' => 'fora do escopo',
            'assumptions' => 'premissas',
            'constraints' => 'restrições',
            'success_criteria' => 'critérios de sucesso',
            'future_vision' => 'visão de futuro',
        ];
    }
}
