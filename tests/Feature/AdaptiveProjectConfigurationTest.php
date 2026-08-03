<?php

namespace Tests\Feature;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveProjectConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_complete_project_without_financial_control_or_client_is_accepted(): void
    {
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)
            ->post(route('projects.store'), $this->projectData($administrator, null, [
                'execution_nature' => ExecutionNature::Internal->value,
                'financial_management_mode' => FinancialManagementMode::NotApplicable->value,
                'management_level' => ManagementLevel::Complete->value,
            ]));

        $project = Project::query()->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertNull($project->client_id);
        $this->assertSame(ExecutionNature::Internal, $project->execution_nature);
        $this->assertSame(FinancialManagementMode::NotApplicable, $project->financial_management_mode);
        $this->assertSame(ManagementLevel::Complete, $project->management_level);
    }

    public function test_contracted_simplified_project_with_fixed_price_is_accepted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $client = Client::factory()->create();

        $this->actingAs($administrator)
            ->post(route('projects.store'), $this->projectData($administrator, $client, [
                'execution_nature' => ExecutionNature::Contracted->value,
                'financial_management_mode' => FinancialManagementMode::FixedPrice->value,
                'management_level' => ManagementLevel::Simplified->value,
                'methodology' => ProjectMethodology::Traditional->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'client_id' => $client->id,
            'execution_nature' => ExecutionNature::Contracted->value,
            'financial_management_mode' => FinancialManagementMode::FixedPrice->value,
            'management_level' => ManagementLevel::Simplified->value,
            'methodology' => ProjectMethodology::Traditional->value,
        ]);
    }

    public function test_changing_management_level_to_complete_records_history_and_preserves_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create([
            'manager_id' => $administrator->id,
            'management_level' => ManagementLevel::Simplified,
        ]);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($administrator)
            ->put(route('projects.update', $project), $this->projectData($administrator, $project->client, [
                'management_level' => ManagementLevel::Complete->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(ManagementLevel::Complete, $project->fresh()->management_level);
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        $activity = ProjectActivity::query()
            ->where('project_id', $project->id)
            ->where('event_type', 'project_configuration_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            ['from' => 'Simplificado', 'to' => 'Completo'],
            $activity->metadata['configuration_changes']['management_level'],
        );
    }

    public function test_reducing_management_level_preserves_existing_records(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create([
            'manager_id' => $administrator->id,
            'management_level' => ManagementLevel::Complete,
        ]);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($administrator)
            ->put(route('projects.update', $project), $this->projectData($administrator, $project->client, [
                'management_level' => ManagementLevel::Simplified->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(ManagementLevel::Simplified, $project->fresh()->management_level);
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_invalid_financial_mode_is_rejected(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('projects.store'), $this->projectData($administrator, null, [
                'financial_management_mode' => 'modalidade-inexistente',
            ]))
            ->assertSessionHasErrors('financial_management_mode');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_form_explains_the_four_independent_dimensions(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Natureza da execução')
            ->assertSee('Tratamento financeiro')
            ->assertSee('Nível de gestão')
            ->assertSee('Metodologia')
            ->assertSee('As quatro dimensões são independentes');
    }

    /** @param array<string, mixed> $overrides */
    private function projectData(User $manager, ?Client $client, array $overrides = []): array
    {
        return array_replace([
            'client_id' => $client?->id,
            'manager_id' => $manager->id,
            'name' => 'Projeto adaptativo de teste',
            'description' => 'Projeto usado para validar a configuração adaptativa.',
            'objective' => 'Validar as dimensões independentes.',
            'justification' => 'Cobrir os requisitos RF081 a RF086.',
            'execution_nature' => ExecutionNature::Internal->value,
            'financial_management_mode' => FinancialManagementMode::NotApplicable->value,
            'management_level' => ManagementLevel::Intermediate->value,
            'methodology' => ProjectMethodology::Kanban->value,
            'status' => ProjectStatus::Planning->value,
            'start_date' => '2026-08-03',
            'expected_end_date' => '2026-12-31',
            'end_date' => null,
            'is_active' => '1',
        ], $overrides);
    }
}
