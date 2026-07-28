<?php

namespace App\Console\Commands;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateFirstAdministrator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sgp:create-first-administrator
                            {--name= : Nome completo do administrador}
                            {--email= : E-mail do administrador}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria de forma controlada o primeiro administrador do SGP';

    public function handle(): int
    {
        if (User::query()
            ->where('global_profile', GlobalProfile::Administrator->value)
            ->where('is_active', true)
            ->exists()) {
            $this->components->error('Já existe um administrador ativo no SGP.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Nome completo')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));
        $password = (string) $this->secret('Senha');
        $passwordConfirmation = (string) $this->secret('Confirme a senha');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->mixedCase()->numbers()->symbols(),
                ],
            ],
            [
                'password.mixed' => 'A senha deve conter letras maiúsculas e minúsculas.',
                'password.numbers' => 'A senha deve conter pelo menos um número.',
                'password.symbols' => 'A senha deve conter pelo menos um símbolo.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $password,
            'global_profile' => GlobalProfile::Administrator,
            'is_active' => true,
        ]);

        $this->components->info('Primeiro administrador criado com sucesso.');

        return self::SUCCESS;
    }
}
