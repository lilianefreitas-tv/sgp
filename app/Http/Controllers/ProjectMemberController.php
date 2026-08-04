<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectMemberController extends Controller
{
    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $project): void {
            ProjectMembership::query()
                ->where('project_id', $project->id)
                ->where('user_id', $data['user_id'])
                ->whereNotIn('role', $data['roles'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'ended_at' => today()]);

            foreach ($data['roles'] as $role) {
                $membership = ProjectMembership::firstOrNew([
                    'project_id' => $project->id,
                    'user_id' => $data['user_id'],
                    'role' => $role,
                ]);
                $membership->is_active = true;
                if (blank($membership->started_at)) {
                    $membership->started_at = today();
                }
                $membership->ended_at = null;
                $membership->save();
            }

            if ((int) $data['user_id'] === $project->manager_id
                && ! in_array(ProjectRole::ProjectManager->value, $data['roles'], true)) {
                $managerMembership = ProjectMembership::firstOrNew([
                    'project_id' => $project->id,
                    'user_id' => $project->manager_id,
                    'role' => ProjectRole::ProjectManager->value,
                ]);
                $managerMembership->is_active = true;
                if (blank($managerMembership->started_at)) {
                    $managerMembership->started_at = $project->start_date ?? today();
                }
                $managerMembership->ended_at = null;
                $managerMembership->save();
            }
        });

        $member = User::findOrFail($data['user_id']);
        ProjectActivity::record(
            $project,
            $request->user(),
            'team_updated',
            'Equipe do projeto atualizada',
            'user',
            $member->id,
            [
                'details' => $member->name.' · '.collect($data['roles'])
                    ->map(fn (string $role) => ProjectRole::from($role)->label())
                    ->join(', '),
            ],
        );

        return to_route('projects.show', $project)->with('success', 'Equipe do projeto atualizada com sucesso.');
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        abort_unless(
            $request->user()->canManageProject($project),
            403,
        );

        if ($project->manager_id === $user->id) {
            return back()->withErrors([
                'team' => 'O responsável principal não pode ser removido da equipe. Defina outro responsável primeiro.',
            ]);
        }

        $updated = ProjectMembership::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => today()]);

        if ($updated > 0) {
            ProjectActivity::record(
                $project,
                $request->user(),
                'member_removed',
                'Participante removido da equipe',
                'user',
                $user->id,
                ['details' => $user->name],
            );
        }

        return to_route('projects.show', $project)->with('success', 'Participante removido da equipe. O histórico do vínculo foi preservado.');
    }
}
