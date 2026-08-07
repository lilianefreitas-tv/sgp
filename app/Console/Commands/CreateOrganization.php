<?php

namespace App\Console\Commands;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAuditService;
use App\Services\StandardDocumentTemplateProvisioner;
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
                            {--owner-email= : E-mail do Administrador principal}';

    protected $description = 'Cria uma organização e vincula seu Administrador principal';

    public function handle(
        OrganizationAuditService $audit,
        StandardDocumentTemplateProvisioner $templates,
    ): int
    {
        $interactive = $this->input->isInteractive();
        $name = trim((string) ($this->option('name') ?: ($interactive ? $this->ask('Nome da organização') : '')));
        $slug = Str::slug(trim((string) ($this->option('slug') ?: $name)));
        $ownerEmail = mb_strtolower(trim((string) (
            $this->option('owner-email') ?: ($interactive ? $this->ask('E-mail do Administrador principal') : '')
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
            $this->components->error('No comando técnico, o Administrador principal deve ser uma conta Superadmin ativa.');

            return self::FAILURE;
        }

        $organization = DB::transaction(function () use ($name, $slug, $owner, $audit, $templates): Organization {
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

            $membership = $owner->organizationMemberships()->create([
                'organization_id' => $organization->id,
                'role_code' => OrganizationRole::Owner,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => ! $owner->organizationMemberships()->where('is_default', true)->exists(),
                'joined_at' => now(),
            ]);

            $provisionedTemplates = $templates->provision($organization, $owner->id);

            $audit->record(
                'organization.provision',
                'success',
                'organization',
                $organization->id,
                [
                    'owner_user_id' => $owner->id,
                    'standard_templates_created' => $provisionedTemplates,
                ],
                $organization->id,
                $owner,
            );

            $audit->record(
                'organization.membership.create',
                'success',
                'organization_membership',
                $membership->id,
                [
                    'user_id' => $owner->id,
                    'role' => OrganizationRole::Owner->value,
                ],
                $organization->id,
                $owner,
            );

            return $organization;
        });

        $this->components->info("Organização [{$organization->name}] criada com sucesso.");

        return self::SUCCESS;
    }
}
