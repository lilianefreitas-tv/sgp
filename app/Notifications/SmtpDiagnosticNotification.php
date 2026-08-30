<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SmtpDiagnosticNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $requestedBy,
        private readonly string $environment,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('PRISMA SGP | Teste de entrega transacional')
            ->greeting('Teste de comunicação concluído')
            ->line('Esta mensagem confirma que o canal transacional de e-mail do PRISMA SGP conseguiu processar uma entrega de teste.')
            ->line("Ambiente: {$this->environment}")
            ->line("Solicitado por: {$this->requestedBy}")
            ->line('Nenhuma credencial ou segredo de SMTP é incluído nesta mensagem.')
            ->salutation('PRISMA SGP');
    }
}
