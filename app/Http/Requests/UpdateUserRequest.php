<?php

namespace App\Http\Requests;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($managedUser)],
            'global_profile' => ['required', Rule::enum(GlobalProfile::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $managedUser */
                $managedUser = $this->route('user');
                $removesAdministrator = $managedUser->isAdministrator()
                    && $this->string('global_profile')->toString() !== GlobalProfile::Administrator->value;
                $deactivatesUser = ! $this->boolean('is_active');

                if ($managedUser->is($this->user()) && ($removesAdministrator || $deactivatesUser)) {
                    $validator->errors()->add('is_active', 'Você não pode retirar seu próprio acesso administrativo ou desativar sua conta.');
                }

                if ($managedUser->isAdministrator()
                    && ($removesAdministrator || $deactivatesUser)
                    && User::query()->where('global_profile', GlobalProfile::Administrator->value)->where('is_active', true)->count() === 1) {
                    $validator->errors()->add('global_profile', 'O sistema deve manter pelo menos um administrador ativo.');
                }
            },
        ];
    }
}
