<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_real_visible_project_data(): void
    {
        $member = User::factory()->create();
        $visible = Project::factory()->create([
            'name' => 'Projeto visível',
            'expected_end_date' => today()->subDay(),
            'status' => ProjectStatus::InProgress,
        ]);
        $hidden = Project::factory()->create(['name' => 'Projeto sigiloso']);
        $this->addMember($visible, $member);
        Requirement::factory()->create([
            'project_id' => $visible->id,
            'status' => RequirementStatus::UnderAnalysis,
        ]);
        Task::factory()->create([
            'project_id' => $visible->id,
            'title' => 'Tarefa atrasada visível',
            'due_date' => today()->subDay(),
            'status' => TaskStatus::InProgress,
        ]);
        Task::factory()->create([
            'project_id' => $hidden->id,
            'title' => 'Tarefa secreta',
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Projeto visível')
            ->assertDontSee('Projeto sigiloso')
            ->assertDontSee('Tarefa secreta')
            ->assertSee('Projetos atrasados')
            ->assertSee('Aguardando análise');
    }

    public function test_dashboard_uses_sgp_title_and_places_calendar_below_panel(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>SGP</title>', false)
            ->assertSee('Bem-vindo(a) ao SGP')
            ->assertDontSee('Abrir calendário')
            ->assertSeeInOrder([
                'Painel',
                'Calendário',
                'Gerenciamento',
            ]);
    }

    public function test_calendar_displays_project_and_task_dates(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create([
            'name' => 'Calendário SGP',
            'start_date' => '2026-08-03',
            'expected_end_date' => '2026-08-28',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Implementar calendário',
            'start_date' => '2026-08-05',
            'due_date' => '2026-08-10',
            'status' => TaskStatus::InProgress,
        ]);

        $this->actingAs($administrator)
            ->get(route('calendar.index', ['month' => '2026-08', 'project' => $project->id]))
            ->assertOk()
            ->assertSee('Calendário SGP')
            ->assertSee('Implementar calendário')
            ->assertSee('Início · '.$task->code)
            ->assertSee('Prazo · '.$task->code);
    }

    public function test_calendar_filters_events_by_type(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create(['start_date' => '2026-08-03']);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-08-05',
            'due_date' => '2026-08-10',
        ]);

        $this->actingAs($administrator)
            ->get(route('calendar.index', [
                'month' => '2026-08',
                'project' => $project->id,
                'type' => 'task_due',
            ]))
            ->assertOk()
            ->assertSee('Prazo · '.$task->code)
            ->assertDontSee('Início · '.$task->code);
    }

    public function test_user_outside_project_cannot_open_project_calendar_or_schedule(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.calendar.index', $project))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('projects.schedule.show', $project))
            ->assertForbidden();
    }

    public function test_project_schedule_groups_planned_tasks_and_lists_unplanned_tasks(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $planned = Task::factory()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'title' => 'Tarefa planejada',
            'start_date' => today()->addDay(),
            'due_date' => today()->addDays(5),
        ]);
        $unplanned = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tarefa sem datas',
            'start_date' => null,
            'due_date' => null,
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.schedule.show', $project))
            ->assertOk()
            ->assertSee($requirement->code)
            ->assertSee($planned->title)
            ->assertSee($unplanned->title)
            ->assertSee('Gantt básico do projeto');
    }

    public function test_dashboard_shows_recent_consolidated_activity(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        ProjectActivity::record(
            $project,
            $administrator,
            'task_updated',
            'Prazo da tarefa revisado',
            'task',
        );

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Prazo da tarefa revisado')
            ->assertSee('Atividades recentes');
    }

    private function addMember(Project $project, User $user): void
    {
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Observer,
            'is_active' => true,
        ]);
    }
}
