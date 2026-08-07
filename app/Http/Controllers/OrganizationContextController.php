<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Services\OrganizationAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationContextController extends Controller
{
    public function update(Request $request, OrganizationAuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);

        if (Gate::denies('changeContext', $organization)) {
            $audit->record('organization.context.change', 'denied', 'organization', $organization->id, [
                'target_organization_id' => $organization->id,
            ]);

            abort(403);
        }

        $audit->record('organization.context.change', 'success', 'organization', $organization->id, [
            'target_organization_id' => $organization->id,
        ]);

        $request->session()->put(EnsureOrganizationContext::SESSION_KEY, $organization->id);
        $request->session()->forget(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY);
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        return to_route('dashboard')->with(
            'success',
            "Organização alterada para {$organization->name}.",
        );
    }
}
