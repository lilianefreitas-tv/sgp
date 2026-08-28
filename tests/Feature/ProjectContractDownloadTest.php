<?php

namespace Tests\Feature;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectContractDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_authorized_project_member_can_download_existing_contract_file(): void
    {
        Storage::fake('local');
        [$organization, $user, $project] = $this->scenario();
        [$contract, $attachment] = $this->contractWithAttachment($project, $user, true);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('contracts.attachments.download', [$contract, $attachment]))
            ->assertOk()
            ->assertDownload('contrato.pdf');

        $this->assertDatabaseHas('organization_audit_events', [
            'organization_id' => $organization->id,
            'action' => 'contract.attachment.download',
            'result' => 'success',
            'resource_id' => $attachment->id,
        ]);
    }

    public function test_missing_contract_file_returns_404_and_records_failure(): void
    {
        Storage::fake('local');
        [$organization, $user, $project] = $this->scenario();
        [$contract, $attachment] = $this->contractWithAttachment($project, $user, false);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('contracts.attachments.download', [$contract, $attachment]))
            ->assertNotFound();

        $this->assertDatabaseHas('organization_audit_events', [
            'organization_id' => $organization->id,
            'action' => 'contract.attachment.download',
            'result' => 'failed',
            'resource_id' => $attachment->id,
        ]);
    }

    public function test_contract_file_from_another_organization_is_not_discoverable(): void
    {
        Storage::fake('local');
        [, $owner, $project] = $this->scenario();
        [$contract, $attachment] = $this->contractWithAttachment($project, $owner, true);

        app(OrganizationContext::class)->clear();
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ]);

        $this->actingAs($otherUser)
            ->withSession(['active_organization_id' => $otherOrganization->id])
            ->get('/contracts/'.$contract->id.'/attachments/'.$attachment->id)
            ->assertNotFound();
    }

    private function contractWithAttachment(Project $project, User $actor, bool $storeFile): array
    {
        $contract = ProjectContract::create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'code' => 'CTR-000001',
            'title' => 'Contrato de teste',
            'contract_kind' => 'private',
            'entry_mode' => ContractEntryMode::Attachment,
            'status' => ContractStatus::Draft,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $path = 'organizations/'.$project->organization_id.'/contracts/'.$contract->id.'/contrato.pdf';
        if ($storeFile) {
            Storage::disk('local')->put($path, '%PDF-1.4 arquivo de teste');
        }
        $attachment = $contract->attachments()->create([
            'organization_id' => $project->organization_id,
            'original_name' => 'contrato.pdf',
            'stored_name' => 'contrato.pdf',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 24,
            'checksum' => hash('sha256', '%PDF-1.4 arquivo de teste'),
            'category' => 'related',
            'created_by' => $actor->id,
        ]);

        return [$contract, $attachment];
    }

    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $user->id]);
        $project->memberships()->create([
            'user_id' => $user->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);

        return [$organization, $user, $project];
    }
}
