<?php

namespace App\Http\Controllers;

use App\Models\SecurityAuditEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSecurityAuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $result = $request->string('result')->toString();
        $action = trim($request->string('action')->toString());
        $acceptedResults = ['', 'sent', 'success', 'failed', 'throttled', 'ignored'];

        if (! in_array($result, $acceptedResults, true)) {
            $result = '';
        }

        $events = SecurityAuditEvent::query()
            ->with(['organization', 'actor', 'targetUser'])
            ->when($result !== '', fn ($query) => $query->where('result', $result))
            ->when($action !== '', fn ($query) => $query->whereLike('action', "%{$action}%", caseSensitive: false))
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString();

        return view('platform.security-audit.index', compact('events', 'result', 'action'));
    }
}
