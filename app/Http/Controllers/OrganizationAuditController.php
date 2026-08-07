<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAuditEvent;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationAuditController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context): View
    {
        abort_unless(
            $request->user()->isAdministrator()
                || in_array($context->role(), [OrganizationRole::Owner, OrganizationRole::Administrator], true),
            403,
        );

        $result = $request->string('result')->toString();
        $action = trim($request->string('action')->toString());

        if (! in_array($result, ['', 'success', 'denied', 'failed'], true)) {
            $result = '';
        }

        $events = OrganizationAuditEvent::query()
            ->with('actor')
            ->when($result !== '', fn ($query) => $query->where('result', $result))
            ->when($action !== '', fn ($query) => $query->whereLike('action', "%{$action}%", caseSensitive: false))
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', compact('events', 'result', 'action'));
    }
}
