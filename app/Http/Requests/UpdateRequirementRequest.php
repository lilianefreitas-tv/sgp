<?php

namespace App\Http\Requests;

class UpdateRequirementRequest extends StoreRequirementRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'change_reason' => 'motivo da alteração',
        ];
    }
}
