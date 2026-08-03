<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);
        Gate::authorize('changeContext', $organization);

        $request->session()->put(EnsureOrganizationContext::SESSION_KEY, $organization->id);
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        return to_route('dashboard')->with(
            'success',
            "Organização alterada para {$organization->name}.",
        );
    }
}
