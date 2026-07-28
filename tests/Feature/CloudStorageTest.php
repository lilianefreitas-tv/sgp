<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProjectRole;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectDocument;
use App\Models\ProjectMembership;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_private_disk_stores_and_downloads_attachment(): void
    {
        Storage::fake('s3');
        config(['sgp.storage.private_disk' => 's3']);
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->addMember($project, $member, ProjectRole::Developer);

        $this->actingAs($member)
            ->post(route('projects.attachments.store', $project), [
                'context' => 'task:'.$task->id,
                'description' => 'Evidência persistente em nuvem.',
                'file' => UploadedFile::fake()->create('evidencia-cloud.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.attachments.index', $project));

        $attachment = ProjectAttachment::query()->firstOrFail();

        $this->assertSame('s3', $attachment->disk);
        Storage::disk('s3')->assertExists($attachment->path);

        $this->actingAs($member)
            ->get(route('projects.attachments.download', [$project, $attachment]))
            ->assertOk();
    }

    public function test_generated_documents_are_uploaded_and_downloaded_from_private_disk(): void
    {
        Storage::fake('s3');
        config(['sgp.storage.private_disk' => 's3']);
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id]);
        $template = DocumentTemplate::query()
            ->where('type', DocumentType::TasksList->value)
            ->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('projects.documents.generate', $project), [
                'document_template_id' => $template->id,
            ])
            ->assertRedirect(route('projects.documents.index', $project))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $document = ProjectDocument::query()->sole();

        Storage::disk('s3')->assertExists($document->docx_path);
        Storage::disk('s3')->assertExists($document->pdf_path);

        $this->actingAs($administrator)
            ->get(route('projects.documents.download', [$project, $document, 'docx']))
            ->assertOk();

        $this->actingAs($administrator)
            ->get(route('projects.documents.download', [$project, $document, 'pdf']))
            ->assertOk();
    }

    private function addMember(Project $project, User $user, ProjectRole $role): void
    {
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'started_at' => today(),
        ]);
    }
}
