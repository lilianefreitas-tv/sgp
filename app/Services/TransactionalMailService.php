<?php

namespace App\Services;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Notifications\SmtpDiagnosticNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionalMailService
{
    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $host = (string) config('mail.mailers.smtp.host');
        $scheme = mb_strtolower((string) config('mail.mailers.smtp.scheme'));
        $username = (string) config('mail.mailers.smtp.username');
        $issues = [];

        if ($mailer !== 'smtp') {
            $issues[] = 'Defina MAIL_MAILER=smtp para o canal transacional desta baseline.';
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
            || str_ends_with(mb_strtolower($fromAddress), '@example.com')) {
            $issues[] = 'Defina um endereço de remetente válido para o domínio do SGP.';
        }

        if ($mailer === 'smtp') {
            if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
                $issues[] = 'Defina o host SMTP do provedor transacional.';
            }

            if (blank(config('mail.mailers.smtp.username'))) {
                $issues[] = 'Defina o usuário SMTP no ambiente.';
            }

            if (blank(config('mail.mailers.smtp.password'))) {
                $issues[] = 'Defina a senha SMTP no ambiente.';
            }

            if ($scheme !== '' && ! in_array($scheme, ['smtp', 'smtps'], true)) {
                $issues[] = 'MAIL_SCHEME deve ser smtp para STARTTLS ou smtps para TLS implícito.';
            }

            if (mb_strtolower($host) === 'smtp.resend.com' && $username !== 'resend') {
                $issues[] = 'Para o Resend, defina MAIL_USERNAME=resend.';
            }

            if (mb_strtolower($host) === 'smtp.resend.com'
                && (int) config('mail.mailers.smtp.port') === 587
                && $scheme !== 'smtp') {
                $issues[] = 'Para o Resend na porta 587, defina MAIL_SCHEME=smtp; o STARTTLS será negociado automaticamente.';
            }
        }

        if (in_array((string) config('queue.default'), ['sync', 'null'], true)) {
            $issues[] = 'Defina uma fila persistente para as mensagens transacionais.';
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'mailer' => $mailer,
            'host' => $host,
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => $scheme ?: 'automático',
            'from_address' => $fromAddress,
            'from_name' => (string) config('mail.from.name'),
            'username_configured' => filled(config('mail.mailers.smtp.username')),
            'password_configured' => filled(config('mail.mailers.smtp.password')),
            'queue_connection' => (string) config('queue.default'),
            'environment' => app()->environment(),
        ];
    }

    public function sendDiagnostic(string $recipient, User $actor, Request $request): void
    {
        $configuration = $this->configuration();

        if (! $configuration['ready']) {
            throw ValidationException::withMessages([
                'recipient' => 'A configuração transacional precisa estar válida antes do teste de entrega.',
            ]);
        }

        try {
            Notification::route('mail', $recipient)->notifyNow(
                new SmtpDiagnosticNotification($actor->name, app()->environment()),
            );

            $this->audit($request, $actor, $recipient, 'sent');
        } catch (Throwable $exception) {
            report($exception);
            $this->audit($request, $actor, $recipient, 'failed', $exception::class);

            throw ValidationException::withMessages([
                'recipient' => 'A entrega não foi concluída. Verifique o provedor e os logs sanitizados do ambiente.',
            ]);
        }
    }

    private function audit(
        Request $request,
        User $actor,
        string $recipient,
        string $result,
        ?string $exceptionClass = null,
    ): void {
        SecurityAuditEvent::query()->create([
            'actor_id' => $actor->id,
            'request_id' => $this->requestId($request),
            'action' => 'mail.smtp.diagnostic',
            'result' => $result,
            'environment' => app()->environment(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => array_filter([
                'recipient_sha256' => hash('sha256', mb_strtolower(trim($recipient))),
                'mailer' => (string) config('mail.default'),
                'exception_class' => $exceptionClass,
            ]),
            'occurred_at' => now(),
        ]);
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-Id');

        return is_string($requestId) && Str::isUuid($requestId)
            ? $requestId
            : (string) Str::uuid();
    }
}
