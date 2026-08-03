<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\OrganizationMembership;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public const SESSION_KEY = 'active_organization_id';

    public function __construct(private readonly OrganizationContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $memberships = $user->organizationMemberships()
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->whereHas('organization', fn ($query) => $query
                ->where('status', OrganizationStatus::Active->value))
            ->with('organization')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $requestedId = $request->session()->get(self::SESSION_KEY);
        $membership = $requestedId === null
            ? null
            : $memberships->firstWhere('organization_id', (int) $requestedId);

        if (! $membership instanceof OrganizationMembership) {
            $membership = $memberships->first();
        }

        abort_if(
            ! $membership instanceof OrganizationMembership,
            403,
            'Sua conta não possui vínculo ativo com uma organização disponível.',
        );

        $request->session()->put(self::SESSION_KEY, $membership->organization_id);
        $this->context->activate($membership, $memberships);

        view()->share([
            'activeOrganization' => $membership->organization,
            'activeOrganizationMembership' => $membership,
            'availableOrganizationMemberships' => $memberships,
        ]);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
