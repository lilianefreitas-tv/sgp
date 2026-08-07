<?php

namespace App\Console\Commands;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOrganizationMemberships extends Command
{
    protected $signature = 'sgp:sync-organization-memberships
                            {organization : Slug da organização inicial}
                            {--dry-run : Simula a regularização sem gravar dados}
                            {--force : Executa sem confirmação interativa}';

    protected $description = 'Vincula contas legadas sem organização ao tenant inicial';

    public function handle(OrganizationAuditService $audit): int
    {
        $organization = Organization::query()
            ->where('slug', $this->argument('organization'))
            ->first();

        if ($organization === null) {
            $this->components->error('A organização informada não existe.');

            return self::FAILURE;
        }

        if ($organization->status !== OrganizationStatus::Active) {
            $this->components->error('A organização informada não está ativa.');

            return self::FAILURE;
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereDoesntHave('organizationMemberships')
            ->orderBy('id')
            ->get();

        $this->table(
            ['Organização', 'Contas ativas sem vínculo', 'Modo'],
            [[
                $organization->name,
                $users->count(),
                $this->option('dry-run') ? 'Simulação' : 'Gravação',
            ]],
        );

        if ($this->option('dry-run')) {
            $this->components->info('Simulação concluída. Nenhum vínculo foi gravado.');

            return self::SUCCESS;
        }

        if ($users->isEmpty()) {
            $this->components->info('Não há contas legadas pendentes de vínculo.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Vincular {$users->count()} conta(s) ativa(s) a [{$organization->name}]?",
        )) {
            $this->components->warn('Operação cancelada.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($organization, $users, $audit): void {
            foreach ($users as $user) {
                $membership = $user->organizationMemberships()->create([
                    'organization_id' => $organization->id,
                    'role_code' => $user->global_profile === GlobalProfile::Administrator
                        ? OrganizationRole::Administrator
                        : OrganizationRole::Member,
                    'status' => OrganizationMembershipStatus::Active,
                    'is_default' => true,
                    'joined_at' => now(),
                ]);

                $audit->record(
                    'organization.membership.create',
                    'success',
                    'organization_membership',
                    $membership->id,
                    [
                        'user_id' => $user->id,
                        'role' => $membership->role_code->value,
                        'source' => 'legacy_sync',
                    ],
                    $organization->id,
                );
            }
        });

        $this->components->info(
            "{$users->count()} conta(s) vinculada(s) a [{$organization->name}].",
        );

        return self::SUCCESS;
    }
}
