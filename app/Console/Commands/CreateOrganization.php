<?php

namespace App\Console\Commands;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateOrganization extends Command
{
    public const BOOTSTRAP_SLUG = 'sgp-instalacao-inicial';

    protected $signature = 'sgp:create-organization
                            {--name= : Nome da organização}
                            {--slug= : Identificador único, gerado pelo nome quando omitido}
                            {--type=company : Tipo da organização}
                            {--timezone=America/Belem : Fuso horário}
                            {--owner-email= : E-mail do proprietário inicial}';

    protected $description = 'Cria uma organização e vincula seu proprietário inicial';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();
        $name = trim((string) ($this->option('name') ?: ($interactive ? $this->ask('Nome da organização') : '')));
        $slug = Str::slug(trim((string) ($this->option('slug') ?: $name)));
        $ownerEmail = mb_strtolower(trim((string) (
            $this->option('owner-email') ?: ($interactive ? $this->ask('E-mail do proprietário inicial') : '')
        )));

        $bootstrap = Organization::query()
            ->where('slug', self::BOOTSTRAP_SLUG)
            ->whereDoesntHave('memberships')
            ->first();

        $validator = Validator::make(
            [
                'name' => $name,
                'slug' => $slug,
                'type' => $this->option('type'),
                'timezone' => $this->option('timezone'),
                'owner_email' => $ownerEmail,
            ],
            [
                'name' => ['required', 'string', 'max:180'],
                'slug' => [
                    'required',
                    'string',
                    'max:120',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique('organizations', 'slug')->ignore($bootstrap?->id),
                ],
                'type' => ['required', Rule::enum(OrganizationType::class)],
                'timezone' => ['required', 'timezone'],
                'owner_email' => ['required', 'email', 'exists:users,email'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $owner = User::query()->where('email', $ownerEmail)->firstOrFail();

        if (! $owner->is_active || $owner->global_profile !== GlobalProfile::Administrator) {
            $this->components->error('O proprietário inicial deve ser um Administrador da Plataforma ativo.');

            return self::FAILURE;
        }

        $organization = DB::transaction(function () use ($name, $slug, $owner): Organization {
            $organization = Organization::query()
                ->where('slug', self::BOOTSTRAP_SLUG)
                ->whereDoesntHave('memberships')
                ->lockForUpdate()
                ->first();

            $attributes = [
                'name' => $name,
                'slug' => $slug,
                'type' => $this->option('type'),
                'status' => OrganizationStatus::Active,
                'timezone' => $this->option('timezone'),
                'settings' => [],
            ];

            if ($organization === null) {
                $organization = Organization::query()->create($attributes);
            } else {
                $organization->update($attributes);
            }

            $owner->organizationMemberships()->create([
                'organization_id' => $organization->id,
                'role_code' => OrganizationRole::Owner,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => ! $owner->organizationMemberships()->where('is_default', true)->exists(),
                'joined_at' => now(),
            ]);

            return $organization;
        });

        $this->components->info("Organização [{$organization->name}] criada com sucesso.");

        return self::SUCCESS;
    }
}
