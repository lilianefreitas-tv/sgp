<?php

namespace App\Http\Controllers;

use App\Enums\ArtifactPublicationAudience;
use App\Enums\ArtifactPublicationMode;
use App\Models\Artifact;
use App\Models\ArtifactPublication;
use App\Services\ArtifactPublicationService;
use App\Services\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactPublicationController extends Controller
{
    public function store(Request $request, Artifact $artifact, ArtifactPublicationService $service): RedirectResponse
    {
        $request->mergeIfMissing([
            'mode' => ArtifactPublicationMode::Individual->value,
            'audience' => ArtifactPublicationAudience::Internal->value,
        ]);
        $data = $request->validate([
            'mode' => ['required', 'in:'.implode(',', array_column(ArtifactPublicationMode::cases(), 'value'))],
            'audience' => ['required', 'in:'.implode(',', array_column(ArtifactPublicationAudience::cases(), 'value'))],
            'purpose' => ['nullable', 'string', 'max:255'],
            'reference_revision_id' => ['nullable', 'integer'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'max:100'],
        ]);

        try {
            $service->publish($artifact, $request->user(), $data);
        } catch (LogicException $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return to_route('artifacts.show', $artifact)->with('success', 'Publicação documental gerada com DOCX, PDF e manifesto de integridade.');
    }

    public function download(ArtifactPublication $publication): StreamedResponse
    {
        abort_unless($publication->organization_id === app(OrganizationContext::class)->id(), 404);
        abort_unless(Storage::disk($publication->disk)->exists($publication->package_path), 404);

        return Storage::disk($publication->disk)->download($publication->package_path);
    }

    public function revoke(Request $request, ArtifactPublication $publication, ArtifactPublicationService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:10000']]);
        try {
            $service->revoke($publication, $data['reason'], $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Publicação revogada sem apagar o pacote nem sua trilha histórica.');
    }
}
