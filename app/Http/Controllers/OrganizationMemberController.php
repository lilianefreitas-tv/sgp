<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\AccountProvisioningService;
use App\Services\OrganizationAuditService;
use App\Services\OrganizationContext;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, OrganizationContext $context): View
    {
        $this->authorizeManagement($context);
        $search = trim((string) $request->query('search'));

        $memberships = OrganizationMembership::query()
            ->where('organization_id', $context->id())
            ->with('user')
            ->when($search, fn ($query) => $query->whereHas('user', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false))))
            ->orderByRaw("case role_code when 'owner' then 1 when 'administrator' then 2 when 'member' then 3 else 4 end")
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('organization-members.index', [
            'memberships' => $memberships,
            'search' => $search,
            'roles' => OrganizationRole::options(),
            'statuses' => $this->statusOptions(),
            'currentRole' => $this->hasPlatformManagement($context)
                ? OrganizationRole::Owner
                : $context->role(),
        ]);
    }

    public function store(
        Request $request,
        OrganizationContext $context,
        AccountProvisioningService $accounts,
        OrganizationAuditService $audit,
        PasswordRecoveryService $recovery,
    ): RedirectResponse {
        $this->authorizeManagement($context);
        $request->merge([
            'account_mode' => $request->input('account_mode', $request->filled('email') ? 'existing' : null),
            'existing_user_email' => $request->input('existing_user_email', $request->input('email')),
        ]);
        $validated = $request->validate([
            'account_mode' => ['required', Rule::in(['existing', 'new'])],
            'existing_user_email' => ['nullable', 'required_if:account_mode,existing', 'email'],
            'new_user_name' => ['nullable', 'required_if:account_mode,new', 'string', 'max:255'],
            'new_user_email' => ['nullable', 'required_if:account_mode,new', 'string', 'lowercase', 'email', 'max:255'],
            'role_code' => ['required', Rule::enum(OrganizationRole::class)],
        ]);
        $role = OrganizationRole::from($validated['role_code']);
        $this->authorizeRoleAssignment($context, $role);

        $result = DB::transaction(function () use ($validated, $context, $accounts, $role, $audit, $request): array {
            $user = $validated['account_mode'] === 'existing'
                ? $accounts->accountByEmail($validated['existing_user_email'])
                : $accounts->createInvitedAccount($validated['new_user_name'], $validated['new_user_email']);
            $membership = OrganizationMembership::query()->firstOrNew([
                'organization_id' => $context->id(),
                'user_id' => $user->id,
            ]);
            $action = $membership->exists ? 'organization.membership.reactivate' : 'organization.membership.create';
            $membership->fill([
                'role_code' => $role,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => $membership->exists
                    ? $membership->is_default
                    : ! $user->organizationMemberships()->where('is_default', true)->exists(),
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();

            $audit->record($action, 'success', 'organization_membership', $membership->id, [
                'user_id' => $user->id,
                'role' => $role->value,
                'account_created' => $validated['account_mode'] === 'new',
            ], $context->id(), $request->user(), $request);

            return ['membership' => $membership, 'account_created' => $validated['account_mode'] === 'new'];
        });

        if ($result['account_created']) {
            $recovery->request(
                $result['membership']->user,
                'password.first_access',
                $request,
                $request->user(),
                $context->id(),
            );
        }

        return to_route('organization-members.index')
            ->with('success', $result['account_created']
                ? "Acesso de {$result['membership']->user->name} salvo e link de primeiro acesso enviado por e-mail."
                : "Acesso de {$result['membership']->user->name} salvo com sucesso.");
    }

    public function sendPasswordResetLink(
        Request $request,
        int $membership,
        OrganizationContext $context,
        PasswordRecoveryService $recovery,
    ): RedirectResponse {
        $this->authorizeManagement($context);
        $target = $this->membership($membership, $context);
        $target->loadMissing('user');

        abort_unless(
            $target->status === OrganizationMembershipStatus::Active && $target->user->is_active,
            422,
            'A conta e o vínculo precisam estar ativos para receber o link.',
        );

        $status = $recovery->request(
            $target->user,
            'password.organization_admin.request',
            $request,
            $request->user(),
            $context->id(),
        );

        return back()->with(
            $status === \Illuminate\Support\Facades\Password::RESET_THROTTLED ? 'warning' : 'success',
            $status === \Illuminate\Support\Facades\Password::RESET_THROTTLED
                ? 'Aguarde antes de solicitar outro link para esta conta.'
                : 'Novo link de redefinição enviado ao e-mail cadastrado.',
        );
    }

    public function update(
        Request $request,
        int $membership,
        OrganizationContext $context,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $this->authorizeManagement($context);
        $target = $this->membership($membership, $context);
        $validated = $request->validate([
            'role_code' => ['required', Rule::enum(OrganizationRole::class)],
            'status' => ['required', Rule::in([
                OrganizationMembershipStatus::Active->value,
                OrganizationMembershipStatus::Suspended->value,
            ])],
        ]);
        $role = OrganizationRole::from($validated['role_code']);
        $status = OrganizationMembershipStatus::from($validated['status']);
        $this->authorizeTarget($context, $target, $role);
        $this->protectLastOwner($target, $role, $status);
        $before = ['role_code' => $target->role_code->value, 'status' => $target->status->value];

        $target->update(['role_code' => $role, 'status' => $status]);
        $this->repairDefaultMembership($target);
        $audit->record('organization.membership.update', 'success', 'organization_membership', $target->id, [
            'user_id' => $target->user_id,
            'before' => $before,
            'after' => ['role_code' => $role->value, 'status' => $status->value],
        ], $context->id(), $request->user(), $request);

        return to_route('organization-members.index')->with('success', 'Acesso atualizado com sucesso.');
    }

    public function destroy(
        Request $request,
        int $membership,
        OrganizationContext $context,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $this->authorizeManagement($context);
        $target = $this->membership($membership, $context);
        $this->authorizeTarget($context, $target);
        $this->protectLastOwner($target, OrganizationRole::Member, OrganizationMembershipStatus::Suspended);
        $metadata = ['user_id' => $target->user_id, 'role' => $target->role_code->value];
        $organizationId = $context->id();
        $user = $target->user;
        $wasDefault = $target->is_default;
        $target->delete();

        if ($wasDefault) {
            $user->organizationMemberships()
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->orderBy('id')
                ->first()?->update(['is_default' => true]);
        }

        $audit->record('organization.membership.delete', 'success', 'organization_membership', $membership,
            $metadata, $organizationId, $request->user(), $request);

        return to_route('organization-members.index')->with('success', 'Acesso removido com sucesso.');
    }

    private function membership(int $id, OrganizationContext $context): OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('organization_id', $context->id())
            ->with('user')
            ->findOrFail($id);
    }

    private function authorizeManagement(OrganizationContext $context): void
    {
        abort_unless(
            $this->hasPlatformManagement($context)
                || in_array($context->role(), [OrganizationRole::Owner, OrganizationRole::Administrator], true),
            403,
        );
    }

    private function authorizeRoleAssignment(OrganizationContext $context, OrganizationRole $role): void
    {
        abort_if($role === OrganizationRole::Owner
            && ! $this->hasPlatformManagement($context)
            && $context->role() !== OrganizationRole::Owner, 403,
            'Somente um Administrador principal pode atribuir esse mesmo nível de acesso.');
    }

    private function authorizeTarget(
        OrganizationContext $context,
        OrganizationMembership $target,
        ?OrganizationRole $newRole = null,
    ): void {
        if ($this->hasPlatformManagement($context)) {
            return;
        }

        if ($context->role() === OrganizationRole::Administrator) {
            abort_if($target->role_code === OrganizationRole::Owner || $newRole === OrganizationRole::Owner, 403,
                'Administradores da organização não podem alterar o acesso de Administradores principais.');
        }
    }

    private function protectLastOwner(
        OrganizationMembership $target,
        OrganizationRole $newRole,
        OrganizationMembershipStatus $newStatus,
    ): void {
        if ($target->role_code !== OrganizationRole::Owner
            || ($newRole === OrganizationRole::Owner && $newStatus === OrganizationMembershipStatus::Active)) {
            return;
        }

        $otherOwners = OrganizationMembership::query()
            ->where('organization_id', $target->organization_id)
            ->where('id', '!=', $target->id)
            ->where('role_code', OrganizationRole::Owner->value)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->exists();

        abort_unless($otherOwners, 422, 'A organização deve manter pelo menos um Administrador principal ativo.');
    }

    private function repairDefaultMembership(OrganizationMembership $membership): void
    {
        if ($membership->status === OrganizationMembershipStatus::Active || ! $membership->is_default) {
            return;
        }

        $membership->update(['is_default' => false]);
        $membership->user->organizationMemberships()
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->orderBy('id')
            ->first()?->update(['is_default' => true]);
    }

    private function hasPlatformManagement(OrganizationContext $context): bool
    {
        return $context->isPlatformAccess()
            && request()->user()?->isAdministrator() === true;
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            OrganizationMembershipStatus::Active->value => OrganizationMembershipStatus::Active->label(),
            OrganizationMembershipStatus::Suspended->value => OrganizationMembershipStatus::Suspended->label(),
        ];
    }
}
