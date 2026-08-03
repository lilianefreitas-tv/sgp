<?php

namespace App\Http\Controllers;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $profile = (string) $request->query('profile');

        $users = User::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false);
            }))
            ->when(in_array($status, ['active', 'inactive'], true),
                fn ($query) => $query->where('is_active', $status === 'active'))
            ->when(array_key_exists($profile, GlobalProfile::options()),
                fn ($query) => $query->where('global_profile', $profile))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search', 'status', 'profile'));
    }

    public function create(): View
    {
        return view('users.create', ['profiles' => GlobalProfile::options()]);
    }

    public function store(
        StoreUserRequest $request,
        OrganizationContext $context,
    ): RedirectResponse
    {
        DB::transaction(function () use ($request, $context): void {
            $user = User::create($request->validated());

            $user->organizationMemberships()->create([
                'organization_id' => $context->id(),
                'role_code' => $user->global_profile === GlobalProfile::Administrator
                    ? OrganizationRole::Administrator
                    : OrganizationRole::Member,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => true,
                'joined_at' => now(),
            ]);
        });

        return to_route('users.index')->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['managedUser' => $user, 'profiles' => GlobalProfile::options()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return to_route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }
}
