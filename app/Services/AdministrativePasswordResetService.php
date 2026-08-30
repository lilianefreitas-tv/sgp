<?php

namespace App\Services;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdministrativePasswordResetService
{
    public function reset(User $target, User $actor, Request $request): string
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'Use a alteração de senha do próprio perfil para modificar sua conta.',
            ]);
        }

        if (! $target->is_active) {
            throw ValidationException::withMessages([
                'user' => 'A conta precisa estar ativa para receber uma senha temporária.',
            ]);
        }

        $temporaryPassword = Str::password(20, true, true, true, false);

        DB::transaction(function () use ($target, $actor, $request, $temporaryPassword): void {
            $target->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'temporary_password_issued_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $revokedSessions = DB::table('sessions')->where('user_id', $target->id)->delete();

            SecurityAuditEvent::query()->create([
                'actor_id' => $actor->id,
                'target_user_id' => $target->id,
                'request_id' => $this->requestId($request),
                'action' => 'password.platform_admin.temporary_reset',
                'result' => 'success',
                'environment' => app()->environment(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => [
                    'must_change_password' => true,
                    'revoked_sessions' => $revokedSessions,
                    'password_disclosed_once' => true,
                ],
                'occurred_at' => now(),
            ]);
        });

        return $temporaryPassword;
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-Id');

        return is_string($requestId) && Str::isUuid($requestId)
            ? $requestId
            : (string) Str::uuid();
    }
}
