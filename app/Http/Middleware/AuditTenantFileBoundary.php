<?php

namespace App\Http\Middleware;

use App\Services\OrganizationAuditService;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditTenantFileBoundary
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OrganizationAuditService $audit,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $target = $this->target($request);

        if ($target !== null
            && $this->context->active()
            && $target['organization_id'] !== $this->context->id()) {
            $this->audit->record(
                $target['action'],
                'denied',
                $target['resource_type'],
                $target['resource_id'],
                [
                    'reason' => 'cross_organization_file_access',
                    'target_organization_id' => $target['organization_id'],
                    'route' => $request->route()?->getName(),
                ],
            );

            abort(404);
        }

        return $next($request);
    }

    /** @return array{organization_id: int, action: string, resource_type: string, resource_id: int}|null */
    private function target(Request $request): ?array
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'projects.attachments.download') {
            return $this->lookup('project_attachments', 'attachment', 'attachment.download', $request);
        }

        if ($routeName === 'projects.documents.download') {
            return $this->lookup('project_documents', 'document', 'document.export', $request);
        }

        return null;
    }

    /** @return array{organization_id: int, action: string, resource_type: string, resource_id: int}|null */
    private function lookup(string $table, string $parameter, string $action, Request $request): ?array
    {
        $value = $request->route($parameter);
        $resourceId = is_object($value) ? (int) $value->getKey() : (int) $value;

        if ($resourceId < 1) {
            return null;
        }

        $organizationId = DB::table($table)
            ->where('id', $resourceId)
            ->value('organization_id');

        if ($organizationId === null) {
            return null;
        }

        return [
            'organization_id' => (int) $organizationId,
            'action' => $action,
            'resource_type' => $parameter,
            'resource_id' => $resourceId,
        ];
    }
}
