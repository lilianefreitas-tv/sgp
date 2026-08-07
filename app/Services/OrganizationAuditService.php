<?php

namespace App\Services;

use App\Models\OrganizationAuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

class OrganizationAuditService
{
    public function __construct(private readonly OrganizationContext $context)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        string $action,
        string $result,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $metadata = [],
        ?int $organizationId = null,
        ?User $actor = null,
        ?Request $request = null,
    ): OrganizationAuditEvent {
        $organizationId ??= $this->context->id();

        if ($organizationId === null) {
            throw new LogicException('A auditoria exige uma organização identificada.');
        }

        if ($request === null && app()->bound('request')) {
            $boundRequest = app('request');
            $request = $boundRequest instanceof Request ? $boundRequest : null;
        }
        $actor ??= Auth::user();
        $requestId = $request?->headers->get('X-Request-Id');

        return OrganizationAuditEvent::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'actor_id' => $actor?->id,
            'request_id' => is_string($requestId) && Str::isUuid($requestId)
                ? $requestId
                : (string) Str::uuid(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'result' => $result,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null
                ? null
                : Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
