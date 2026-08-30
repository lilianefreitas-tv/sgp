<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdministrativePasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PlatformPasswordResetController extends Controller
{
    public function confirm(User $user): View
    {
        abort_if(! $user->is_active, 422, 'A conta precisa estar ativa para receber uma senha temporária.');

        return view('platform.users.temporary-password-confirm', [
            'managedUser' => $user,
        ]);
    }

    public function store(
        Request $request,
        User $user,
        AdministrativePasswordResetService $passwords,
    ): Response {
        $temporaryPassword = $passwords->reset($user, $request->user(), $request);

        return response()
            ->view('platform.users.temporary-password', [
                'managedUser' => $user->fresh(),
                'temporaryPassword' => $temporaryPassword,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }
}
