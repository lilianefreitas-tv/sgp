<?php

namespace Tests\Feature;

use App\Enums\ArtifactPublicationAudience;
use App\Enums\ArtifactPublicationMode;
use App\Enums\ArtifactType;
use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\DocumentRole;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Artifact;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\ArtifactPublicationService;
use App\Services\ArtifactRevisionService;
use App\Services\ArtifactWorkflowService;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ArtifactPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('artifact-publications');
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_publishes_exact_approved_revision_as_verifiable_package(): void
    {
        [$organization, $actor, $artifact] = $this->scenario();
        $publication = app(ArtifactPublicationService::class)->publish($artifact, $actor);

        Storage::disk('local')->assertExists($publication->package_path);
        $this->assertSame($artifact->revisions()->sole()->id, $publication->artifact_revision_id);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($publication->package_path)), $publication->package_checksum);
        $this->assertSame('sgp.artifact-publication.v2', $publication->manifest['schema']);
        $this->assertCount(2, $publication->manifest['files']);
    }

    public function test_publication_is_idempotent_for_same_approval(): void
    {
        [, $actor, $artifact] = $this->scenario();
        $first = app(ArtifactPublicationService::class)->publish($artifact, $actor);
        $second = app(ArtifactPublicationService::class)->publish($artifact, $actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $artifact->publications()->count());
    }

    public function test_draft_revision_cannot_be_published(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->artifact($actor);

        $this->expectException(LogicException::class);
        app(ArtifactPublicationService::class)->publish($artifact, $actor);
    }

    public function test_same_approved_revision_can_have_distinct_authorized_publications(): void
    {
        [, $actor, $artifact] = $this->scenario();
        $service = app(ArtifactPublicationService::class);

        $internal = $service->publish($artifact, $actor, [
            'mode' => ArtifactPublicationMode::Individual->value,
            'audience' => ArtifactPublicationAudience::Internal->value,
            'purpose' => 'Uso da equipe.',
        ]);
        $client = $service->publish($artifact, $actor, [
            'mode' => ArtifactPublicationMode::Individual->value,
            'audience' => ArtifactPublicationAudience::Client->value,
            'purpose' => 'Apresentação ao cliente.',
        ]);

        $this->assertNotSame($internal->id, $client->id);
        $this->assertSame(2, $artifact->publications()->count());
        $this->assertSame(ArtifactPublicationAudience::Client, $client->audience);
        $this->assertSame('client', $client->manifest['publication']['audience']);
    }

    public function test_custom_publication_requires_at_least_one_section(): void
    {
        [, $actor, $artifact] = $this->scenario();

        $this->expectException(LogicException::class);
        app(ArtifactPublicationService::class)->publish($artifact, $actor, [
            'mode' => ArtifactPublicationMode::Custom->value,
            'audience' => ArtifactPublicationAudience::Internal->value,
            'sections' => [],
        ]);
    }

    public function test_comparative_publication_rejects_reference_from_another_artifact(): void
    {
        [, $actor, $artifact] = $this->scenario();
        $foreignRevision = $this->artifact($actor)->revisions()->sole();

        $this->expectException(LogicException::class);
        app(ArtifactPublicationService::class)->publish($artifact, $actor, [
            'mode' => ArtifactPublicationMode::Comparative->value,
            'audience' => ArtifactPublicationAudience::Audit->value,
            'reference_revision_id' => $foreignRevision->id,
        ]);
    }

    public function test_human_form_fields_are_transformed_without_leaking_into_service_contract(): void
    {
        [$organization, $actor] = $this->actor();
        $existing = $this->artifact($actor);
        $initiative = $existing->initiative;

        foreach ([ArtifactType::InitiativeRecord, ArtifactType::StructuredRecord] as $type) {
            $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
                ->post(route('initiatives.artifacts.store', $initiative), [
                    'type' => $type->value,
                    'title' => "Artefato {$type->value}",
                    'description' => 'Criado pelo formulário humano.',
                    'summary' => 'Resumo compreensível.',
                    'objective' => 'Validar o contrato.',
                    'scope' => 'Contexto da iniciativa.',
                    'body' => 'Conteúdo sem JSON.',
                    'schema_version' => 1,
                    'change_reason' => 'Registro inicial.',
                ])->assertRedirect(route('initiatives.artifacts.index', $initiative))->assertSessionHasNoErrors();
        }

        $created = Artifact::query()->where('title', 'like', 'Artefato %')->with('revisions')->get();
        $this->assertCount(2, $created);
        $this->assertSame('Resumo compreensível.', $created->first()->revisions->sole()->content['resumo']);
        $this->assertSame('Conteúdo sem JSON.', $created->last()->revisions->sole()->content['conteudo']);
    }

    /** @return array{0: Organization, 1: User, 2: Artifact} */
    private function scenario(): array
    {
        [$organization, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $actor, DocumentRole::Author, $actor);
        $workflow->assign($artifact, $actor, DocumentRole::Reviewer, $actor);
        $workflow->assign($artifact, $actor, DocumentRole::Approver, $actor);
        $round = $workflow->submit($artifact, $actor, 'Pronto para publicação.');
        $workflow->decide($round, $actor, ArtifactWorkflowDecisionType::ForwardedForApproval, 'Revisão técnica concluída.');
        $workflow->decide($round, $actor, ArtifactWorkflowDecisionType::Approved, 'Aprovado para emissão.');

        return [$organization, $actor, $artifact->fresh()];
    }

    private function artifact(User $actor): Artifact
    {
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa documental', 'context' => 'Contexto', 'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal, 'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential, 'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');

        return app(ArtifactRevisionService::class)->create([
            'initiative_id' => $initiative->id, 'type' => ArtifactType::InitiativeRecord, 'title' => 'Plano de referência',
            'description' => 'Documento estruturado para publicação.', 'content' => ['objetivo' => 'Validar a emissão', 'escopo' => 'P04.3'],
            'metadata' => null, 'schema_version' => 1, 'change_reason' => 'Inicial.',
        ], $actor);
    }

    /** @return array{0: Organization, 1: User} */
    private function actor(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        return [$organization, $actor];
    }
}
