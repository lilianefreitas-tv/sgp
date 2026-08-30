<?php

namespace App\Http\Requests;

use App\Enums\HomologationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectHomologationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $project !== null && $this->user()->canHomologateProject($project);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::enum(HomologationStatus::class)],
            'baseline_id' => ['nullable', 'integer'],
            'commit_reference' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', 'string', 'max:200'],
            'scope' => ['required', 'string', 'max:10000'],
            'decision_notes' => ['required', 'string', 'max:10000'],
        ];
    }
}
