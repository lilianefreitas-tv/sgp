<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectHistoryController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectHistoryService $history,
    ): View {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );

        $filter = (string) $request->query('type');
        if (! array_key_exists($filter, ProjectHistoryService::filters())) {
            $filter = '';
        }

        $project->load('manager');

        return view('history.index', [
            'project' => $project,
            'events' => $history->paginate($project, $filter),
            'filters' => ProjectHistoryService::filters(),
            'filter' => $filter,
        ]);
    }
}
