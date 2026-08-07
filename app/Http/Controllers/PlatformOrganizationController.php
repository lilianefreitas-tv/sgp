<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Services\AccountProvisioningService;
use App\Services\OrganizationAuditService;
use App\Services\PasswordRecoveryService;
use App\Services\StandardDocumentTemplateProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformOrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $organizations = Organization::query()
            ->withCount([
                'memberships as active_members_count' => fn ($query) => $query
                    ->where('status', OrganizationMembershipStatus::Active->value),
            ])
            ->with(['memberships' => fn ($query) => $query
                ->where('role_code', OrganizationRole::Owner->value)
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->with('user')])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('slug', "%{$search}%", caseSensitive: false);
            }))
            ->when(array_key_exists($status, $this->statusOptions()),
                fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('platform.organizations.index', compact('organizations', 'search', 'status'));
    }

    public function create(): View
    {
        return view('platform.organizations.create', $this->formData(new Organization()));
    }

    public function store(
        Request $request,
        AccountProvisioningService $accounts,
        StandardDocumentTemplateProvisioner $templates,
        OrganizationAuditService $audit,
        PasswordRecoveryService $recovery,
    ): RedirectResponse {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);
        $validated = $request->validate($this->rules() + [
            'account_mode' => ['required', Rule::in(['existing', 'new'])],
            'administrator_user_id' => ['nullable', 'required_if:account_mode,existing', 'integer', 'exists:users,id'],
            'new_user_name' => ['nullable', 'required_if:account_mode,new', 'string', 'max:255'],
            'new_user_email' => ['nullable', 'required_if:account_mode,new', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($validated, $accounts, $templates, $audit, $request): array {
            $owner = $validated['account_mode'] === 'existing'
                ? $accounts->existingActiveAccount((int) $validated['administrator_user_id'])
                : $accounts->createInvitedAccount($validated['new_user_name'], $validated['new_user_email']);
            $organization = Organization::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'type' => $validated['type'],
                'status' => OrganizationStatus::Active,
                'timezone' => $validated['timezone'],
                'settings' => [],
            ]);

            $membership = $organization->memberships()->create([
                'user_id' => $owner->id,
                'role_code' => OrganizationRole::Owner,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => ! $owner->organizationMemberships()->where('is_default', true)->exists(),
                'joined_at' => now(),
            ]);

            $createdTemplates = $templates->provision($organization, $request->user()->id);

            $audit->record('organization.create', 'success', 'organization', $organization->id, [
                'owner_user_id' => $owner->id,
                'standard_templates_created' => $createdTemplates,
            ], $organization->id, $request->user(), $request);
            $audit->record('organization.membership.create', 'success', 'organization_membership', $membership->id, [
                'user_id' => $owner->id,
                'role' => OrganizationRole::Owner->value,
                'account_created' => $validated['account_mode'] === 'new',
            ], $organization->id, $request->user(), $request);

            return [
                'organization' => $organization,
                'owner' => $owner,
                'account_created' => $validated['account_mode'] === 'new',
            ];
        });

        if ($result['account_created']) {
            $recovery->request(
                $result['owner'],
                'password.first_access',
                $request,
                $request->user(),
                $result['organization']->id,
            );
        }

        return to_route('platform.organizations.edit', $result['organization'])
            ->with('success', $result['account_created']
                ? 'Organização criada e link de primeiro acesso enviado ao proprietário.'
                : 'Organização criada e provisionada com sucesso.');
    }

    public function edit(Organization $organization): View
    {
        $organization->load(['memberships' => fn ($query) => $query
            ->where('role_code', OrganizationRole::Owner->value)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->with('user')]);

        return view('platform.organizations.edit', $this->formData($organization));
    }

    public function access(
        Request $request,
        Organization $organization,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        abort_unless(
            $organization->status === OrganizationStatus::Active,
            422,
            'Somente organizações ativas podem ser acessadas.',
        );

        $request->session()->put([
            EnsureOrganizationContext::SESSION_KEY => $organization->id,
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY => $organization->id,
        ]);
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        $audit->record('platform.organization.access', 'success', 'organization', $organization->id, [
            'access_mode' => 'superadmin_temporary',
        ], $organization->id, $request->user(), $request);

        return to_route('dashboard')->with(
            'success',
            "Acesso temporário iniciado em {$organization->name}.",
        );
    }

    public function leave(
        Request $request,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $organizationId = $request->session()->get(
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY,
        );

        if ($organizationId !== null) {
            $organization = Organization::query()->find((int) $organizationId);

            if ($organization instanceof Organization) {
                $audit->record('platform.organization.leave', 'success', 'organization', $organization->id, [
                    'access_mode' => 'superadmin_temporary',
                ], $organization->id, $request->user(), $request);
            }
        }

        $request->session()->forget([
            EnsureOrganizationContext::SESSION_KEY,
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY,
            'url.intended',
        ]);
        $request->session()->regenerateToken();

        return to_route('platform.organizations.index')->with(
            'success',
            'Acesso temporário encerrado. Você voltou à Administração da Plataforma.',
        );
    }

    public function update(
        Request $request,
        Organization $organization,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);
        $validated = $request->validate($this->rules($organization, false));
        $before = $organization->only(['name', 'slug', 'type', 'status', 'timezone']);

        $organization->update($validated);

        $audit->record('organization.update', 'success', 'organization', $organization->id, [
            'before' => $before,
            'after' => $organization->fresh()->only(['name', 'slug', 'type', 'status', 'timezone']),
        ], $organization->id, $request->user(), $request);

        return to_route('platform.organizations.edit', $organization)
            ->with('success', 'Organização atualizada com sucesso.');
    }

    /** @return array<string, mixed> */
    private function formData(Organization $organization): array
    {
        return [
            'organization' => $organization,
            'types' => OrganizationType::options(),
            'statuses' => $this->statusOptions(),
            'administratorCandidates' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(?Organization $organization = null, bool $creating = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')->ignore($organization?->id)],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'timezone' => ['required', 'timezone'],
        ];

        if (! $creating) {
            $rules['status'] = ['required', Rule::enum(OrganizationStatus::class)];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(OrganizationStatus::cases())
            ->mapWithKeys(fn (OrganizationStatus $status) => [$status->value => $status->label()])
            ->all();
    }
}
