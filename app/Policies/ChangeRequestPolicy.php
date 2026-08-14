<?php

namespace App\Policies;

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
            && $user->canContributeToProject($changeRequest->project)
            && ($user->canManageProject($changeRequest->project)
                || $changeRequest->requester_id === $user->id);
    }

    public function submit(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest);
    }

    public function startAnalysis(User $user, ChangeRequest $changeRequest): bool
    {
        return $changeRequest->state === ChangeRequestState::Submitted
            && $this->canAnalyze($user, $changeRequest);
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
        return $changeRequest->state === ChangeRequestState::UnderAnalysis
            && $user->canManageProject($changeRequest->project);
    }

    public function cancel(User $user, ChangeRequest $changeRequest): bool
    {
        return in_array($changeRequest->state, [
            ChangeRequestState::Draft,
            ChangeRequestState::Submitted,
            ChangeRequestState::UnderAnalysis,
            ChangeRequestState::Returned,
        ], true) && ($user->canManageProject($changeRequest->project)
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
            ], true) && $this->canAnalyze($user, $changeRequest));
    }

    private function canAnalyze(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->canManageProject($changeRequest->project)
            || $changeRequest->analyst_id === $user->id
            || $user->hasProjectRole(ProjectRole::RequirementsAnalyst, $changeRequest->project);
    }
}
