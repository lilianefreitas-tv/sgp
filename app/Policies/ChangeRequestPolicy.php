<?php

namespace App\Policies;

use App\Enums\ChangeRequestAnalysisStatus;
use App\Enums\ChangeRequestState;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\User;

class ChangeRequestPolicy
{
    public function view(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->canAccessProject($changeRequest->project);
    }

    public function update(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state->isEditable()
            && $this->canRequest($user, $changeRequest)
            && ($this->isProjectManager($user, $changeRequest)
                || $changeRequest->requester_id === $user->id);
    }

    public function submit(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest);
    }

    public function startAnalysis(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::Submitted
            && $this->isAnalysisRole($user, $changeRequest)
            && ($changeRequest->analyst_id === null || $changeRequest->analyst_id === $user->id);
    }

    public function assignAnalyst(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::Submitted
            && $this->isProjectManager($user, $changeRequest);
    }

    public function returnForAdjustment(User $user, ChangeRequest $changeRequest): bool
    {
        return in_array($changeRequest->state, [
            ChangeRequestState::Submitted,
            ChangeRequestState::UnderAnalysis,
        ], true) && $this->canAnalyze($user, $changeRequest);
    }

    public function decide(User $user, ChangeRequest $changeRequest): bool
    {
        if ($changeRequest->state !== ChangeRequestState::UnderAnalysis
            || ! $this->isProjectManager($user, $changeRequest)) {
            return false;
        }

        return $changeRequest->currentImpactAnalysis?->status === ChangeRequestAnalysisStatus::Completed;
    }

    public function analyzeImpact(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::UnderAnalysis
            && ($this->isProjectManager($user, $changeRequest)
                || $changeRequest->analyst_id === $user->id);
    }

    public function cancel(User $user, ChangeRequest $changeRequest): bool
    {
        return in_array($changeRequest->state, [
            ChangeRequestState::Draft,
            ChangeRequestState::Submitted,
            ChangeRequestState::UnderAnalysis,
            ChangeRequestState::Returned,
        ], true) && ($this->isProjectManager($user, $changeRequest)
            || ($user->canContributeToProject($changeRequest->project)
                && ($changeRequest->requester_id === $user->id
                    || $changeRequest->analyst_id === $user->id)));
    }

    public function manageAttachments(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest)
            || (in_array($changeRequest->state, [
                ChangeRequestState::Submitted,
                ChangeRequestState::UnderAnalysis,
            ], true) && $this->canAnalyze($user, $changeRequest))
            || ($changeRequest->state === ChangeRequestState::Approved
                && $this->canImplement($user, $changeRequest));
    }

    public function updateImplementation(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::Approved
            && $this->canImplement($user, $changeRequest);
    }

    public function startImplementation(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->updateImplementation($user, $changeRequest);
    }

    public function completeImplementation(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::Approved
            && $this->isProjectManager($user, $changeRequest);
    }

    private function canAnalyze(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->isProjectManager($user, $changeRequest)
            || $changeRequest->analyst_id === $user->id;
    }

    private function canImplement(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->isProjectManager($user, $changeRequest)
            || $changeRequest->implementation?->responsible_id === $user->id;
    }

    private function canRequest(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->canContributeToProject($changeRequest->project)
            && $changeRequest->project->hasActiveMember($user)
            && $user->projectMemberships()
                ->where('project_id', $changeRequest->project_id)
                ->where('is_active', true)
                ->where('role', '!=', ProjectRole::Observer->value)
                ->exists();
    }

    private function isAnalysisRole(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->isProjectManager($user, $changeRequest)
            || $user->hasProjectRole(ProjectRole::RequirementsAnalyst, $changeRequest->project);
    }

    private function isProjectManager(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->hasProjectRole(ProjectRole::ProjectManager, $changeRequest->project);
    }
}
