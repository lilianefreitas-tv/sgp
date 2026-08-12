<?php

namespace App\Http\Controllers;

use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Initiative;
use App\Models\Project;
use App\Services\ArtifactRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use LogicException;

class ArtifactController extends Controller
{
    public function initiativeIndex(Initiative $initiative): View
    {
        return $this->index('initiative', $initiative);
    }

    public function projectIndex(Project $project): View
    {
        return $this->index('project', $project);
    }

    public function storeForInitiative(Request $request, Initiative $initiative, ArtifactRevisionService $service): RedirectResponse
    {
        return $this->store($request, $service, ['initiative_id' => $initiative->id], 'initiatives.artifacts.index', $initiative);
    }

    public function storeForProject(Request $request, Project $project, ArtifactRevisionService $service): RedirectResponse
    {
        return $this->store($request, $service, ['project_id' => $project->id], 'projects.artifacts.index', $project);
    }

    public function show(Artifact $artifact): View
    {
        return view('artifacts.show', ['artifact' => $artifact->load(['initiative', 'project', 'revisions.changedBy'])]);
    }

    public function revise(Request $request, Artifact $artifact, ArtifactRevisionService $service): RedirectResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:1048576'], 'metadata' => ['nullable', 'string', 'max:1048576'], 'schema_version' => ['required', 'integer', 'min:1', 'max:65535'], 'change_reason' => ['required', 'string', 'max:10000']]);
        try {
            $service->revise($artifact, $this->decodeJson($data['content']), blank($data['metadata']) ? null : $this->decodeJson($data['metadata']), $data['schema_version'], $data['change_reason'], $request->user());
        } catch (LogicException|JsonException $exception) {
            return back()->withErrors(['content' => $exception->getMessage()])->withInput();
        }

        return to_route('artifacts.show', $artifact)->with('success', 'Nova revisão registrada.');
    }

    public function archive(Request $request, Artifact $artifact, ArtifactRevisionService $service): RedirectResponse
    {
        $data = $request->validate(['archive_reason' => ['required', 'string', 'max:10000']]);
        try {
            $service->archive($artifact, $data['archive_reason'], $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['archive_reason' => $exception->getMessage()]);
        }

        return to_route('artifacts.show', $artifact)->with('success', 'Artefato arquivado.');
    }

    private function index(string $parentType, Initiative|Project $parent): View
    {
        $artifacts = $parent->artifacts()->withCount('revisions')->latest()->get();

        return view('artifacts.index', compact('parentType', 'parent', 'artifacts'));
    }

    /** @param array<string, int> $parent @param array<int|string, mixed> $routeParameters */
    private function store(Request $request, ArtifactRevisionService $service, array $parent, string $route, Initiative|Project $routeParameters): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:'.implode(',', array_keys(ArtifactType::options()))], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'], 'content' => ['required', 'string', 'max:1048576'], 'metadata' => ['nullable', 'string', 'max:1048576'], 'schema_version' => ['required', 'integer', 'min:1', 'max:65535'], 'change_reason' => ['required', 'string', 'max:10000']]);
        try {
            $service->create([...$data, ...$parent, 'content' => $this->decodeJson($data['content']), 'metadata' => blank($data['metadata']) ? null : $this->decodeJson($data['metadata'])], $request->user());
        } catch (LogicException|JsonException $exception) {
            return back()->withErrors(['content' => $exception->getMessage()])->withInput();
        }

        return to_route($route, $routeParameters)->with('success', 'Artefato estruturado criado.');
    }

    /** @return array<mixed> */
    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new LogicException('O JSON deve conter um objeto ou lista.');
        }

        return $decoded;
    }
}
