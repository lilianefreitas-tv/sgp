<?php

namespace App\Services;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordRecoveryService
{
    public function request(
        ?User $target,
        string $action,
        Request $request,
        ?User $actor = null,
        ?int $organizationId = null,
    ): string {
        $status = $target?->is_active
            ? Password::broker()->sendResetLink([
                'email' => $target->email,
                'is_active' => true,
            ])
            : Password::INVALID_USER;

        SecurityAuditEvent::query()->create([
            'organization_id' => $organizationId,
            'actor_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'request_id' => $this->requestId($request),
            'action' => $action,
            'result' => match ($status) {
                Password::RESET_LINK_SENT => 'sent',
                Password::RESET_THROTTLED => 'throttled',
                default => 'ignored',
            },
            'environment' => app()->environment(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => ['delivery' => 'email'],
            'occurred_at' => now(),
        ]);

        return $status;
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-Id');

        return is_string($requestId) && Str::isUuid($requestId)
            ? $requestId
            : (string) Str::uuid();
    }
}
