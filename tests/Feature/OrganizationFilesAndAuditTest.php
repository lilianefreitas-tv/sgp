<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectDocument;
use App\Models\ProjectMembership;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationFilesAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_is_stored_in_tenant_path_with_hash_and_audit(): void
    {
        Storage::fake('local');
        [$organization, $user, $project] = $this->tenant(ProjectRole::Developer);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->post(route('projects.attachments.store', $project), [
                'context' => 'task:'.$task->id,
                'file' => UploadedFile::fake()->create('evidencia.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.attachments.index', $project));

        $attachment = ProjectAttachment::query()->sole();
        $this->assertStringStartsWith(
            "organizations/{$organization->id}/projects/{$project->id}/attachments/",
            $attachment->path,
        );
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($attachment->path)),
            $attachment->sha256,
        );
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->get(route('projects.attachments.download', [$project, $attachment]))
            ->assertOk();

        $this->assertAudit($organization, 'attachment.upload', 'success');
        $this->assertAudit($organization, 'attachment.download', 'success');
    }

    public function test_generated_docx_and_pdf_are_tenant_scoped_hashed_and_audited(): void
    {
        Storage::fake('local');
        [$organization, $user, $project] = $this->tenant(ProjectRole::ProjectManager, true);
        Task::factory()->create(['project_id' => $project->id]);
        $template = new DocumentTemplate([
            'name' => 'Lista de tarefas isolada',
            'type' => DocumentType::TasksList,
            'version' => 1,
            'is_active' => true,
        ]);
        $template->organization_id = $organization->id;
        $template->save();

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->post(route('projects.documents.generate', $project), [
                'document_template_id' => $template->id,
            ])
            ->assertRedirect(route('projects.documents.index', $project));

        $document = ProjectDocument::query()->sole();
        $prefix = "organizations/{$organization->id}/projects/{$project->id}/generated-documents/";
        $this->assertStringStartsWith($prefix, $document->docx_path);
        $this->assertStringStartsWith($prefix, $document->pdf_path);
        $this->assertSame('local', $document->disk);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $document->docx_sha256);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $document->pdf_sha256);
        Storage::disk('local')->assertExists($document->docx_path);
        Storage::disk('local')->assertExists($document->pdf_path);
        $this->assertAudit($organization, 'document.generate', 'success');
    }

    public function test_cross_tenant_attachment_download_is_hidden_and_audited(): void
    {
        Storage::fake('local');
        [$organizationA, $userA, $projectA] = $this->tenant(ProjectRole::Developer);
        [$organizationB, $userB, $projectB] = $this->tenant(ProjectRole::Developer);
        Storage::disk('local')->put('organizations/b/foreign.pdf', 'foreign');
        $attachment = $this->attachment($organizationB, $projectB, $userB, 'organizations/b/foreign.pdf');

        $this->actingAs($userA)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organizationA->id])
            ->get(route('projects.attachments.download', [$projectB, $attachment]))
            ->assertNotFound();

        $event = $this->assertAudit($organizationA, 'attachment.download', 'denied');
        $this->assertSame($organizationB->id, $event->metadata['target_organization_id']);
        $this->assertSame($attachment->id, $event->resource_id);
        $this->assertNotSame($projectA->organization_id, $projectB->organization_id);
    }

    public function test_cross_tenant_document_export_is_hidden_and_audited(): void
    {
        Storage::fake('local');
        [$organizationA, $userA] = $this->tenant(ProjectRole::Developer);
        [$organizationB, $userB, $projectB] = $this->tenant(ProjectRole::ProjectManager, true);
        $template = $this->template($organizationB);
        $document = $this->document($organizationB, $projectB, $template, $userB);

        $this->actingAs($userA)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organizationA->id])
            ->get(route('projects.documents.download', [$projectB, $document, 'pdf']))
            ->assertNotFound();

        $event = $this->assertAudit($organizationA, 'document.export', 'denied');
        $this->assertSame($organizationB->id, $event->metadata['target_organization_id']);
    }

    public function test_authorized_audit_view_lists_only_active_organization(): void
    {
        [$organizationA, $ownerA] = $this->tenant(ProjectRole::ProjectManager, true, OrganizationRole::Owner);
        [$organizationB] = $this->tenant(ProjectRole::ProjectManager, true, OrganizationRole::Owner);
        $this->auditEvent($organizationA, 'event.visible.a');
        $this->auditEvent($organizationB, 'event.hidden.b');

        $this->actingAs($ownerA)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organizationA->id])
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('event.visible.a')
            ->assertDontSee('event.hidden.b');
    }

    public function test_audit_view_displays_event_in_active_organization_timezone(): void
    {
        [$organization, $owner] = $this->tenant(
            ProjectRole::ProjectManager,
            true,
            OrganizationRole::Owner,
        );
        $organization->update(['timezone' => 'America/Belem']);

        OrganizationAuditEvent::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'action' => 'event.timezone.belem',
            'result' => 'success',
            'occurred_at' => CarbonImmutable::create(2026, 8, 4, 12, 34, 56, 'UTC'),
        ]);

        $this->actingAs($owner)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('04/08/2026 09:34:56')
            ->assertDontSee('04/08/2026 12:34:56');
    }

    public function test_ordinary_member_cannot_consult_organization_audit(): void
    {
        [$organization, $member] = $this->tenant(ProjectRole::Developer, false, OrganizationRole::Member);

        $this->actingAs($member)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_context_changes_and_denials_are_audited(): void
    {
        [$organizationA, $user] = $this->tenant(ProjectRole::ProjectManager, true, OrganizationRole::Owner);
        $organizationB = Organization::factory()->create();
        $organizationC = Organization::factory()->create();
        $this->membership($organizationB, $user, OrganizationRole::Administrator, false);

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organizationA->id])
            ->put(route('organization-context.update'), ['organization_id' => $organizationB->id])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organizationA->id])
            ->put(route('organization-context.update'), ['organization_id' => $organizationC->id])
            ->assertForbidden();

        $this->assertAudit($organizationA, 'organization.context.change', 'success');
        $this->assertAudit($organizationA, 'organization.context.change', 'denied');
    }

    public function test_file_reconciliation_simulates_then_copies_and_updates_legacy_file(): void
    {
        Storage::fake('local');
        [$organization, $user, $project] = $this->tenant(ProjectRole::Developer);
        Storage::disk('local')->put('project-attachments/legacy.pdf', 'legacy-safe');
        $attachment = $this->attachment($organization, $project, $user, 'project-attachments/legacy.pdf');

        $this->artisan('sgp:reconcile-organization-files')
            ->expectsOutputToContain('Simulação concluída')
            ->assertSuccessful();

        $attachment->refresh();
        $this->assertSame('project-attachments/legacy.pdf', $attachment->path);
        $this->assertNull($attachment->sha256);

        $this->artisan('sgp:reconcile-organization-files', ['--apply' => true])
            ->expectsOutputToContain('Reconciliação aplicada')
            ->assertSuccessful();

        $attachment->refresh();
        $this->assertStringStartsWith(
            "organizations/{$organization->id}/projects/{$project->id}/attachments/",
            $attachment->path,
        );
        $this->assertSame(hash('sha256', 'legacy-safe'), $attachment->sha256);
        Storage::disk('local')->assertExists('project-attachments/legacy.pdf');
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertAudit($organization, 'organization.files.reconciled', 'success');
    }

    /** @return array{Organization, User, Project} */
    private function tenant(
        ProjectRole $projectRole,
        bool $administrator = false,
        OrganizationRole $organizationRole = OrganizationRole::Member,
    ): array {
        $organization = Organization::factory()->create();
        $user = $administrator
            ? User::factory()->administrator()->create()
            : User::factory()->create();
        $this->membership($organization, $user, $organizationRole);
        $project = Project::unguarded(fn () => Project::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => null,
            'manager_id' => $user->id,
        ]));
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $projectRole,
            'is_active' => true,
            'started_at' => today(),
        ]);

        return [$organization, $user, $project];
    }

    private function membership(
        Organization $organization,
        User $user,
        OrganizationRole $role,
        bool $default = true,
    ): OrganizationMembership {
        return OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => $role,
            'status' => OrganizationMembershipStatus::Active,
            'is_default' => $default,
            'joined_at' => now(),
        ]);
    }

    private function attachment(
        Organization $organization,
        Project $project,
        User $user,
        string $path,
    ): ProjectAttachment {
        $attachment = new ProjectAttachment([
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename($path),
            'size_bytes' => 11,
        ]);
        $attachment->organization_id = $organization->id;
        $attachment->save();

        return $attachment;
    }

    private function template(Organization $organization): DocumentTemplate
    {
        $template = new DocumentTemplate([
            'name' => 'Modelo de exportação',
            'type' => DocumentType::TasksList,
            'version' => 1,
            'is_active' => true,
        ]);
        $template->organization_id = $organization->id;
        $template->save();

        return $template;
    }

    private function document(
        Organization $organization,
        Project $project,
        DocumentTemplate $template,
        User $user,
    ): ProjectDocument {
        $document = new ProjectDocument([
            'project_id' => $project->id,
            'document_template_id' => $template->id,
            'generated_by' => $user->id,
            'type' => DocumentType::TasksList,
            'title' => 'Lista de tarefas',
            'version' => 1,
            'disk' => 'local',
            'docx_path' => 'organizations/b/document.docx',
            'pdf_path' => 'organizations/b/document.pdf',
            'docx_file_name' => 'document.docx',
            'pdf_file_name' => 'document.pdf',
            'generated_at' => now(),
        ]);
        $document->organization_id = $organization->id;
        $document->save();

        return $document;
    }

    private function auditEvent(Organization $organization, string $action): OrganizationAuditEvent
    {
        return OrganizationAuditEvent::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'action' => $action,
            'result' => 'success',
            'occurred_at' => now(),
        ]);
    }

    private function assertAudit(
        Organization $organization,
        string $action,
        string $result,
    ): OrganizationAuditEvent {
        $event = OrganizationAuditEvent::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('action', $action)
            ->where('result', $result)
            ->latest('id')
            ->first();

        $this->assertNotNull($event, "Evento {$action}/{$result} não encontrado.");

        return $event;
    }
}
