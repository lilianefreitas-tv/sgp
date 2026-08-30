<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequiredPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active
            && $request->user()->must_change_password
            && ! $request->routeIs(
                'password.required.edit',
                'password.required.update',
                'logout',
            )) {
            return redirect()->route('password.required.edit');
        }

        return $next($request);
    }
}
