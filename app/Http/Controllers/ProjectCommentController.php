<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectCommentRequest;
use App\Models\Project;
use App\Services\ProjectContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectCommentController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectContextService $contexts,
    ): View {
        $this->ensureCanView($request, $project);

        $comments = $project->comments()
            ->with('author')
            ->latest()
            ->paginate(15);
        $contexts->addLabels($project, $comments);

        return view('comments.index', [
            'project' => $project,
            'comments' => $comments,
            'contextOptions' => $contexts->options($project),
        ]);
    }

    public function store(
        StoreProjectCommentRequest $request,
        Project $project,
        ProjectContextService $contexts,
    ): RedirectResponse {
        $this->ensureCanView($request, $project);
        $context = $contexts->resolve($project, $request->string('context')->toString());

        $project->comments()->create([
            'user_id' => $request->user()->id,
            'context_type' => $context['type'],
            'context_id' => $context['id'],
            'body' => trim($request->string('body')->toString()),
        ]);

        return to_route('projects.comments.index', $project)
            ->with('success', 'Comentário registrado com sucesso.');
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }
}
