<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public const SESSION_KEY = 'active_organization_id';

    public const PLATFORM_ACCESS_SESSION_KEY = 'platform_access_organization_id';

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
        $platformAccessId = $request->session()->get(self::PLATFORM_ACCESS_SESSION_KEY);

        if ($user->isAdministrator()
            && $requestedId !== null
            && $platformAccessId !== null
            && (int) $requestedId === (int) $platformAccessId) {
            $organization = Organization::query()
                ->whereKey((int) $platformAccessId)
                ->where('status', OrganizationStatus::Active->value)
                ->first();

            if ($organization instanceof Organization) {
                $this->context->activatePlatformAccess($organization, $memberships);

                view()->share([
                    'activeOrganization' => $organization,
                    'activeOrganizationMembership' => null,
                    'availableOrganizationMemberships' => $memberships,
                    'platformOrganizationAccess' => true,
                ]);

                try {
                    return $next($request);
                } finally {
                    $this->context->clear();
                }
            }

            $request->session()->forget([
                self::SESSION_KEY,
                self::PLATFORM_ACCESS_SESSION_KEY,
            ]);
        }

        $membership = $requestedId === null
            ? null
            : $memberships->firstWhere('organization_id', (int) $requestedId);

        if (! $membership instanceof OrganizationMembership) {
            $membership = $memberships->first();
        }

        if (! $membership instanceof OrganizationMembership) {
            $request->session()->forget([
                self::SESSION_KEY,
                self::PLATFORM_ACCESS_SESSION_KEY,
            ]);

            if ($user->isAdministrator()) {
                return to_route('platform.organizations.index')->with(
                    'info',
                    'Selecione uma organização para acessá-la como Superadmin.',
                );
            }

            abort(403, 'Sua conta não possui vínculo ativo com uma organização disponível.');
        }

        $request->session()->put(self::SESSION_KEY, $membership->organization_id);
        $request->session()->forget(self::PLATFORM_ACCESS_SESSION_KEY);
        $this->context->activate($membership, $memberships);

        view()->share([
            'activeOrganization' => $membership->organization,
            'activeOrganizationMembership' => $membership,
            'availableOrganizationMemberships' => $memberships,
            'platformOrganizationAccess' => false,
        ]);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
