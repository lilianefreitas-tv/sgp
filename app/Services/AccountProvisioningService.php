<?php

namespace App\Services;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountProvisioningService
{
    public function existingActiveAccount(int $userId): User
    {
        $user = User::query()->findOrFail($userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'administrator_user_id' => 'A conta selecionada está inativa.',
            ]);
        }

        return $user;
    }

    /** @return array{user: User, activation_url: string} */
    public function createInvitedAccount(string $name, string $email): array
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if (User::query()->where('email', $normalizedEmail)->exists()) {
            throw ValidationException::withMessages([
                'new_user_email' => 'Já existe uma conta com este e-mail. Use a opção de vincular conta existente.',
            ]);
        }

        $user = User::query()->create([
            'name' => trim($name),
            'email' => $normalizedEmail,
            'password' => Str::password(48),
            'global_profile' => GlobalProfile::User,
            'is_active' => true,
        ]);

        $token = Password::broker()->createToken($user);

        return [
            'user' => $user,
            'activation_url' => route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
        ];
    }

    public function accountByEmail(string $email): User
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'existing_user_email' => 'Nenhuma conta ativa foi encontrada com este e-mail.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'existing_user_email' => 'A conta informada está inativa.',
            ]);
        }

        return $user;
    }
}
