<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     */
    public function store(Request $request, PasswordRecoveryService $recovery): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim((string) $request->input('email'))))
            ->where('is_active', true)
            ->first();

        $recovery->request($user, 'password.public.request', $request);

        return back()->with(
            'status',
            'Se existir uma conta ativa com esse e-mail, enviaremos as instruções de redefinição.',
        );
    }
}
