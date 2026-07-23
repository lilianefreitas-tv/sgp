<?php

namespace App\Http\Requests;

use App\Enums\GlobalProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'global_profile' => ['required', Rule::enum(GlobalProfile::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
