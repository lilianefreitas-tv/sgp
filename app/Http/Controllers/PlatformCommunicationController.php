<?php

namespace App\Http\Controllers;

use App\Services\TransactionalMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformCommunicationController extends Controller
{
    public function index(Request $request, TransactionalMailService $mail): View
    {
        return view('platform.communication.index', [
            'configuration' => $mail->configuration(),
            'defaultRecipient' => $request->user()->email,
        ]);
    }

    public function test(Request $request, TransactionalMailService $mail): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:255'],
        ]);

        $mail->sendDiagnostic($validated['recipient'], $request->user(), $request);

        return back()->with('success', 'Mensagem de teste processada pelo canal transacional. Confirme o recebimento no endereço informado.');
    }
}
