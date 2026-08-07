<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Project;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()?->canManageProject($project) === true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists('organization_memberships', 'user_id')->where(fn ($query) => $query
                    ->where('organization_id', app(OrganizationContext::class)->id())
                    ->where('status', OrganizationMembershipStatus::Active->value)),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(ProjectRole::class)],
        ];
    }
}
