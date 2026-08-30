<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RequiredPasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route($request->user()->isAdministrator()
                ? 'platform.organizations.index'
                : 'dashboard');
        }

        return view('auth.required-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ser diferente da senha temporária.',
            ]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $user = $request->user();
            $currentSession = $request->session()->getId();

            $user->forceFill([
                'password' => $validated['password'],
                'must_change_password' => false,
                'temporary_password_issued_at' => null,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSession)
                ->delete();

            SecurityAuditEvent::query()->create([
                'actor_id' => $user->id,
                'target_user_id' => $user->id,
                'request_id' => (string) Str::uuid(),
                'action' => 'password.required_change.completed',
                'result' => 'success',
                'environment' => app()->environment(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => ['temporary_password_replaced' => true],
                'occurred_at' => now(),
            ]);
        });

        $request->session()->regenerate();

        return redirect()->route($request->user()->isAdministrator()
            ? 'platform.organizations.index'
            : 'dashboard')
            ->with('success', 'Senha definitiva cadastrada com sucesso.');
    }
}
