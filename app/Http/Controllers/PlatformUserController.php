<?php

namespace App\Http\Controllers;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Models\User;
use App\Services\AccountProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $profile = (string) $request->query('profile');

        $users = User::query()
            ->with(['organizationMemberships' => fn ($query) => $query
                ->with('organization')
                ->orderBy('organization_id')])
            ->withCount(['organizationMemberships as active_organizations_count' => fn ($query) => $query
                ->where('status', OrganizationMembershipStatus::Active->value)])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false);
            }))
            ->when(in_array($status, ['active', 'inactive'], true),
                fn ($query) => $query->where('is_active', $status === 'active'))
            ->when(array_key_exists($profile, GlobalProfile::options()),
                fn ($query) => $query->where('global_profile', $profile))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('platform.users.index', compact('users', 'search', 'status', 'profile'));
    }

    public function create(): View
    {
        return view('platform.users.create', [
            'profiles' => GlobalProfile::options(),
        ]);
    }

    public function store(
        Request $request,
        AccountProvisioningService $accounts,
    ): RedirectResponse {
        $validated = $request->validate([
            'new_user_name' => ['required', 'string', 'max:255'],
            'new_user_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'global_profile' => ['required', Rule::enum(GlobalProfile::class)],
        ]);

        $result = DB::transaction(function () use ($validated, $accounts): array {
            $account = $accounts->createInvitedAccount(
                $validated['new_user_name'],
                $validated['new_user_email'],
            );
            $account['user']->update([
                'global_profile' => GlobalProfile::from($validated['global_profile']),
            ]);

            return $account;
        });

        return to_route('platform.users.index')
            ->with('success', "Conta de {$result['user']->name} criada com sucesso.")
            ->with('activation_url', $result['activation_url']);
    }

    public function edit(User $user): View
    {
        $user->load(['organizationMemberships.organization']);

        return view('platform.users.edit', [
            'managedUser' => $user,
            'profiles' => GlobalProfile::options(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'global_profile' => ['required', Rule::enum(GlobalProfile::class)],
            'is_active' => ['required', 'boolean'],
        ]);

        $profile = GlobalProfile::from($validated['global_profile']);
        $isActive = (bool) $validated['is_active'];
        $removesActiveSuperadmin = $user->isSuperAdmin()
            && $user->is_active
            && ($profile !== GlobalProfile::Administrator || ! $isActive);

        if ($user->is($request->user()) && $removesActiveSuperadmin) {
            throw ValidationException::withMessages([
                'is_active' => 'Você não pode retirar seu próprio acesso de Superadmin nem desativar sua conta.',
            ]);
        }

        if ($removesActiveSuperadmin
            && User::query()
                ->where('global_profile', GlobalProfile::Administrator->value)
                ->where('is_active', true)
                ->count() === 1) {
            throw ValidationException::withMessages([
                'global_profile' => 'O SGP deve manter pelo menos uma conta Superadmin ativa.',
            ]);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'global_profile' => $profile,
            'is_active' => $isActive,
        ]);

        return to_route('platform.users.index')
            ->with('success', 'Conta atualizada com sucesso. Os vínculos organizacionais foram preservados.');
    }
}
