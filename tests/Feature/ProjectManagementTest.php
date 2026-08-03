<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_client_and_project(): void
    {
        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->create();

        $this->actingAs($administrator)
            ->post(route('clients.store'), [
                'name' => 'Promotoria de Justiça de Altamira',
                'type' => ClientType::Unit->value,
                'document' => null,
                'email' => 'contato@example.com',
                'phone' => null,
                'contact_name' => 'Representante',
                'is_active' => '1',
            ])
            ->assertRedirect(route('clients.index'));

        $client = Client::firstOrFail();

        $response = $this->actingAs($administrator)
            ->post(route('projects.store'), $this->projectData($client, $manager));

        $project = Project::firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('PRJ-0001', $project->code);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::ProjectManager->value,
            'is_active' => true,
        ]);
    }

    public function test_user_without_manager_role_cannot_create_projects_or_clients(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->projectData($client, $user))
            ->assertForbidden();
    }

    public function test_user_only_sees_projects_where_they_are_an_active_member(): void
    {
        $user = User::factory()->create();
        $visibleProject = Project::factory()->create();
        $hiddenProject = Project::factory()->create();

        ProjectMembership::create([
            'project_id' => $visibleProject->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Observer,
            'is_active' => true,
            'started_at' => today(),
        ]);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($visibleProject->name)
            ->assertDontSee($hiddenProject->name);

        $this->actingAs($user)
            ->get(route('projects.show', $hiddenProject))
            ->assertForbidden();
    }

    public function test_project_manager_can_assign_multiple_roles_to_a_participant(): void
    {
        $manager = User::factory()->create();
        $participant = User::factory()->create();
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $this->makeManager($project, $manager);

        $this->actingAs($manager)
            ->post(route('projects.members.store', $project), [
                'user_id' => $participant->id,
                'roles' => [
                    ProjectRole::Developer->value,
                    ProjectRole::Dba->value,
                ],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $participant->id,
            'role' => ProjectRole::Developer->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $participant->id,
            'role' => ProjectRole::Dba->value,
            'is_active' => true,
        ]);
    }

    public function test_primary_manager_cannot_be_removed_from_team(): void
    {
        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->create();
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $this->makeManager($project, $manager);

        $this->actingAs($administrator)
            ->delete(route('projects.members.destroy', [$project, $manager]))
            ->assertSessionHasErrors('team');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::ProjectManager->value,
            'is_active' => true,
        ]);
    }

    public function test_archiving_project_does_not_delete_it(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $this->actingAs($administrator)
            ->patch(route('projects.archive', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'is_active' => false,
        ]);
        $this->assertNotNull($project->fresh()->archived_at);
    }

    public function test_completed_project_receives_end_date_when_omitted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->create();
        $client = Client::factory()->create();
        $data = $this->projectData($client, $manager);
        $data['status'] = ProjectStatus::Completed->value;
        $data['end_date'] = null;

        $this->actingAs($administrator)
            ->post(route('projects.store'), $data)
            ->assertSessionHasNoErrors();

        $this->assertSame(today()->toDateString(), Project::firstOrFail()->end_date->toDateString());
    }

    /** @return array<string, mixed> */
    private function projectData(Client $client, User $manager): array
    {
        return [
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'name' => 'Sistema de Gestão de Projetos de Software',
            'description' => 'Descrição do projeto.',
            'objective' => 'Organizar o ciclo de vida dos projetos.',
            'justification' => 'Centralizar informações e artefatos.',
            'execution_nature' => ExecutionNature::Internal->value,
            'financial_management_mode' => FinancialManagementMode::NotApplicable->value,
            'management_level' => ManagementLevel::Intermediate->value,
            'methodology' => ProjectMethodology::Kanban->value,
            'status' => ProjectStatus::Planning->value,
            'start_date' => '2026-07-23',
            'expected_end_date' => '2026-12-31',
            'end_date' => null,
            'is_active' => '1',
        ];
    }

    private function makeManager(Project $project, User $user): void
    {
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);
    }
}
