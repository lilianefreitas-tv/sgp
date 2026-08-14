<?php

namespace App\Services;

use App\Enums\ArtifactPublicationAudience;
use App\Enums\ArtifactPublicationMode;
use App\Enums\ArtifactPublicationStatus;
use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\ArtifactWorkflowState;
use App\Enums\DocumentRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Artifact;
use App\Models\ArtifactPublication;
use App\Models\ArtifactRevision;
use App\Models\ArtifactWorkflowRound;
use App\Models\DocumentRoleAssignment;
use App\Models\User;
use App\Support\ArtifactPublicationPresenter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

class ArtifactPublicationService
{
    public function __construct(private readonly OrganizationContext $context) {}

    /** @param array<string, mixed> $options */
    public function publish(Artifact $artifact, User $actor, array $options = []): ArtifactPublication
    {
        return DB::transaction(function () use ($artifact, $actor, $options): ArtifactPublication {
            $locked = $this->authorizedArtifact($artifact, $actor);
            if ($locked->archived_at !== null) {
                throw new LogicException('Artefatos arquivados não podem ser publicados.');
            }
            $round = $locked->workflowRounds()->where('state', ArtifactWorkflowState::Approved->value)
                ->whereHas('revision', fn ($query) => $query->where('sequence', $locked->current_revision_sequence))
                ->with(['revision', 'decisions.actor'])->lockForUpdate()->latest('sequence')->first();
            if ($round === null) {
                throw new LogicException('A revisão atual precisa estar aprovada antes da publicação.');
            }
            $this->authorizePublisher($locked, $round, $actor);
            $publicationOptions = $this->publicationOptions($locked, $round->revision, $options);
            $fingerprintData = [
                'mode' => $publicationOptions['mode']->value,
                'audience' => $publicationOptions['audience']->value,
                'purpose' => $publicationOptions['purpose'],
                'reference_revision_id' => $publicationOptions['reference_revision_id'],
                'sections' => $publicationOptions['sections'],
            ];
            $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $existing = ArtifactPublication::query()->where('artifact_revision_id', $round->artifact_revision_id)
                ->where('artifact_workflow_round_id', $round->id)
                ->where('publication_fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $lastPublication = $locked->publications()
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();
            $sequence = ((int) ($lastPublication?->sequence ?? 0)) + 1;
            $base = "artifact-publications/{$locked->organization_id}/{$locked->id}/{$sequence}";
            $content = $this->publicationContent($locked, $round->revision, $publicationOptions);
            $manifest = $this->manifest($locked, $round, $sequence, $actor, $publicationOptions, $fingerprint);
            $files = $this->renderFiles($base, $locked, $round, $manifest, $content);
            $manifest['files'] = $files;
            Storage::disk('local')->put("{$base}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $packagePath = "{$base}/{$locked->code}-publicacao-{$sequence}.zip";
            $this->zip($base, $packagePath, [...array_column($files, 'name'), 'manifest.json']);

            return ArtifactPublication::query()->create([
                'organization_id' => $locked->organization_id,
                'artifact_id' => $locked->id,
                'artifact_revision_id' => $round->artifact_revision_id,
                'artifact_workflow_round_id' => $round->id,
                'sequence' => $sequence,
                'mode' => $publicationOptions['mode'],
                'audience' => $publicationOptions['audience'],
                'purpose' => $publicationOptions['purpose'],
                'reference_revision_id' => $publicationOptions['reference_revision_id'],
                'selection' => $publicationOptions['sections'],
                'publication_fingerprint' => $fingerprint,
                'status' => ArtifactPublicationStatus::Published,
                'disk' => 'local',
                'package_path' => $packagePath,
                'package_checksum' => hash_file('sha256', Storage::disk('local')->path($packagePath)),
                'manifest' => $manifest,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);
        });
    }

    public function revoke(ArtifactPublication $publication, string $reason, User $actor): ArtifactPublication
    {
        return DB::transaction(function () use ($publication, $reason, $actor): ArtifactPublication {
            $publication = ArtifactPublication::query()->lockForUpdate()->findOrFail($publication->id);
            $artifact = $this->authorizedArtifact($publication->artifact, $actor);
            $this->authorizePublisher($artifact, $publication->workflowRound, $actor);
            if ($publication->status === ArtifactPublicationStatus::Revoked) {
                return $publication;
            }
            if (trim($reason) === '') {
                throw new LogicException('Informe o motivo da revogação.');
            }
            $publication->update(['status' => ArtifactPublicationStatus::Revoked, 'revoked_by' => $actor->id, 'revoked_at' => now(), 'revocation_reason' => trim($reason)]);

            return $publication;
        });
    }

    private function authorizedArtifact(Artifact $artifact, User $actor): Artifact
    {
        if (! $actor->is_active || ! $this->context->active() || $this->context->id() !== $artifact->organization_id) {
            throw new LogicException('Contexto organizacional inválido.');
        }
        if ($actor->isSuperAdmin() && ! $this->context->isPlatformAccess()) {
            throw new LogicException('Superadministradores exigem acesso temporário explícito.');
        }
        if (! $actor->isSuperAdmin() && ! $actor->organizationMemberships()->where('organization_id', $artifact->organization_id)->where('status', OrganizationMembershipStatus::Active->value)->exists()) {
            throw new LogicException('Membership ativo obrigatório.');
        }

        return Artifact::query()->where('organization_id', $artifact->organization_id)->with(['initiative', 'project', 'publications'])->lockForUpdate()->findOrFail($artifact->id);
    }

    private function authorizePublisher(Artifact $artifact, ArtifactWorkflowRound $round, User $actor): void
    {
        if ($actor->isSuperAdmin() || $actor->administersCurrentOrganization()) {
            return;
        }
        $parent = $artifact->initiative_id !== null ? ['initiative_id', $artifact->initiative_id] : ['project_id', $artifact->project_id];
        $assigned = DocumentRoleAssignment::query()->where('organization_id', $artifact->organization_id)
            ->where(array_key_first($parent), current($parent))->where('user_id', $actor->id)
            ->where('role', DocumentRole::Approver->value)->whereNull('effective_until')->exists();
        $approved = $round->decisions()->where('actor_id', $actor->id)->where('decision', ArtifactWorkflowDecisionType::Approved->value)->exists();
        if (! $assigned || ! $approved) {
            throw new LogicException('A publicação exige administrador ou aprovador responsável pela decisão vigente.');
        }
    }

    /** @return array<string, mixed> */
    /** @param array<string, mixed> $options */
    private function manifest(Artifact $artifact, ArtifactWorkflowRound $round, int $sequence, User $actor, array $options, string $fingerprint): array
    {
        return [
            'schema' => 'sgp.artifact-publication.v2',
            'artifact' => ['id' => $artifact->id, 'code' => $artifact->code, 'title' => $artifact->title, 'type' => $artifact->type->value],
            'context' => $artifact->initiative_id !== null
                ? ['type' => 'initiative', 'id' => $artifact->initiative_id, 'code' => $artifact->initiative?->code, 'name' => $artifact->initiative?->title]
                : ['type' => 'project', 'id' => $artifact->project_id, 'code' => $artifact->project?->code, 'name' => $artifact->project?->name],
            'revision' => ['id' => $round->revision->id, 'sequence' => $round->revision->sequence, 'checksum' => $round->revision->checksum, 'schema_version' => $round->revision->schema_version],
            'approval' => ['round_id' => $round->id, 'round_sequence' => $round->sequence, 'closed_at' => $round->closed_at?->toIso8601String()],
            'publication' => [
                'sequence' => $sequence,
                'mode' => $options['mode']->value,
                'audience' => $options['audience']->value,
                'purpose' => $options['purpose'],
                'reference_revision_id' => $options['reference_revision_id'],
                'sections' => $options['sections'],
                'fingerprint' => $fingerprint,
                'published_by' => $actor->id,
                'published_at' => now()->toIso8601String(),
            ],
        ];
    }

    /** @return array<int, array{name: string, format: string, checksum: string, size: int}> */
    private function renderFiles(string $base, Artifact $artifact, ArtifactWorkflowRound $round, array $manifest, array $content): array
    {
        Storage::disk('local')->makeDirectory($base);
        $stem = "{$artifact->code}-r{$round->revision->sequence}";
        $docxName = "{$stem}.docx";
        $pdfName = "{$stem}.pdf";
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle($artifact->title, 1);
        $section->addText("{$artifact->code} | Revisão {$round->revision->sequence} | {$artifact->type->label()}");
        $section->addText($artifact->description ?? '');
        foreach (ArtifactPublicationPresenter::sections($content) as $node) {
            $this->addWordNode($section, $node, 2);
        }
        $section->addTitle('Rastreabilidade', 2);
        $section->addText("Checksum da revisão: {$round->revision->checksum}");
        IOFactory::createWriter($phpWord, 'Word2007')->save(Storage::disk('local')->path("{$base}/{$docxName}"));

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('artifacts.publication-pdf', ['artifact' => $artifact, 'round' => $round, 'manifest' => $manifest, 'publicationContent' => $content])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        file_put_contents(Storage::disk('local')->path("{$base}/{$pdfName}"), $dompdf->output());

        return collect([[$docxName, 'docx'], [$pdfName, 'pdf']])->map(function (array $file) use ($base): array {
            $path = Storage::disk('local')->path("{$base}/{$file[0]}");

            return ['name' => $file[0], 'format' => $file[1], 'checksum' => hash_file('sha256', $path), 'size' => filesize($path)];
        })->all();
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function publicationOptions(Artifact $artifact, ArtifactRevision $revision, array $options): array
    {
        $mode = ArtifactPublicationMode::tryFrom((string) ($options['mode'] ?? ArtifactPublicationMode::Individual->value))
            ?? ArtifactPublicationMode::Individual;
        $audience = ArtifactPublicationAudience::tryFrom((string) ($options['audience'] ?? ArtifactPublicationAudience::Internal->value))
            ?? ArtifactPublicationAudience::Internal;
        $referenceId = filled($options['reference_revision_id'] ?? null) ? (int) $options['reference_revision_id'] : null;
        $sections = array_values(array_unique(array_filter(array_map('strval', $options['sections'] ?? []))));

        if (in_array($mode, [ArtifactPublicationMode::Incremental, ArtifactPublicationMode::Comparative], true)) {
            if ($referenceId === null || $referenceId === $revision->id) {
                throw new LogicException('Selecione uma revisão anterior do mesmo documento como referência.');
            }
            $validReference = ArtifactRevision::query()->where('id', $referenceId)
                ->where('artifact_id', $artifact->id)->where('organization_id', $artifact->organization_id)
                ->where('sequence', '<', $revision->sequence)->exists();
            if (! $validReference) {
                throw new LogicException('A revisão de referência não pertence ao histórico anterior deste documento.');
            }
        } else {
            $referenceId = null;
        }

        if ($mode === ArtifactPublicationMode::Custom && $sections === []) {
            throw new LogicException('Selecione ao menos uma seção para o pacote personalizado.');
        }

        return [
            'mode' => $mode,
            'audience' => $audience,
            'purpose' => filled($options['purpose'] ?? null) ? trim((string) $options['purpose']) : null,
            'reference_revision_id' => $referenceId,
            'sections' => $mode === ArtifactPublicationMode::Custom ? $sections : [],
        ];
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function publicationContent(Artifact $artifact, ArtifactRevision $revision, array $options): array
    {
        $current = $revision->content;
        $mode = $options['mode'];
        $reference = $options['reference_revision_id'] === null
            ? null
            : ArtifactRevision::query()->findOrFail($options['reference_revision_id']);

        $content = match ($mode) {
            ArtifactPublicationMode::Consolidated => [
                'documentos_vigentes' => $this->contextDocuments($artifact),
            ],
            ArtifactPublicationMode::Specialized => [
                'categoria' => $artifact->type->label(),
                'documentos_vigentes' => $this->contextDocuments($artifact, true),
            ],
            ArtifactPublicationMode::Incremental => ['alteracoes_desde_a_referencia' => $this->differences($reference?->content ?? [], $current)],
            ArtifactPublicationMode::Comparative => [
                'revisao_de_referencia' => $reference?->content ?? [],
                'revisao_vigente' => $current,
                'diferencas' => $this->differences($reference?->content ?? [], $current),
            ],
            ArtifactPublicationMode::Custom => array_intersect_key($current, array_flip($options['sections'])),
            default => $current,
        };

        return $options['audience'] === ArtifactPublicationAudience::Client
            ? $this->filterClientContent($content)
            : $content;
    }

    /** @return array<int, array<string, mixed>> */
    private function contextDocuments(Artifact $artifact, bool $sameType = false): array
    {
        $query = Artifact::query()
            ->where('organization_id', $artifact->organization_id)
            ->whereNull('archived_at')
            ->where('workflow_state', ArtifactWorkflowState::Approved->value)
            ->when(
                $artifact->initiative_id !== null,
                fn ($builder) => $builder->where('initiative_id', $artifact->initiative_id),
                fn ($builder) => $builder->where('project_id', $artifact->project_id),
            )
            ->when($sameType, fn ($builder) => $builder->where('type', $artifact->type->value))
            ->with('revisions')
            ->orderBy('code');

        return $query->get()->map(function (Artifact $document): array {
            $revision = $document->revisions->firstWhere('sequence', $document->current_revision_sequence);

            return [
                'codigo' => $document->code,
                'titulo' => $document->title,
                'tipo' => $document->type->label(),
                'revisao' => $revision?->sequence,
                'checksum' => $revision?->checksum,
                'conteudo' => $revision?->content ?? [],
            ];
        })->all();
    }

    /** @return array{adicionados: array<string, mixed>, alterados: array<string, mixed>, removidos: array<string, mixed>} */
    private function differences(array $before, array $after, string $prefix = ''): array
    {
        $changes = ['adicionados' => [], 'alterados' => [], 'removidos' => []];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (! array_key_exists($key, $before)) {
                $changes['adicionados'][$path] = $after[$key];
            } elseif (! array_key_exists($key, $after)) {
                $changes['removidos'][$path] = $before[$key];
            } elseif (is_array($before[$key]) && is_array($after[$key])) {
                $nested = $this->differences($before[$key], $after[$key], $path);
                foreach ($changes as $kind => $_) {
                    $changes[$kind] = [...$changes[$kind], ...$nested[$kind]];
                }
            } elseif ($before[$key] !== $after[$key]) {
                $changes['alterados'][$path] = ['anterior' => $before[$key], 'vigente' => $after[$key]];
            }
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private function filterClientContent(array $content): array
    {
        $sensitive = ['custo', 'cost', 'margem', 'margin', 'preco_interno', 'formacao_preco', 'observacao_interna'];
        foreach ($content as $key => $value) {
            $normalized = mb_strtolower((string) $key);
            if (collect($sensitive)->contains(fn (string $term) => str_contains($normalized, $term))) {
                unset($content[$key]);
            } elseif (is_array($value)) {
                $content[$key] = $this->filterClientContent($value);
            }
        }

        return $content;
    }

    /** @param array{label: string, value: ?string, children: array} $node */
    private function addWordNode(object $section, array $node, int $level): void
    {
        if ($node['children'] === []) {
            $section->addText($node['label'], ['bold' => true]);
            $section->addText($node['value'] ?? 'Não informado');

            return;
        }

        $headingLevel = min($level, 4);
        $section->addTitle($node['label'], $headingLevel);
        foreach ($node['children'] as $child) {
            $this->addWordNode($section, $child, $level + 1);
        }
    }

    private function zip(string $base, string $packagePath, array $files): void
    {
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk('local')->path($packagePath), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new LogicException('Não foi possível montar o pacote documental.');
        }
        foreach ($files as $file) {
            $zip->addFile(Storage::disk('local')->path("{$base}/{$file}"), $file);
        }
        $zip->close();
    }
}
