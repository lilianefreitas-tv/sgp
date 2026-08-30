<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Comunicação transacional</h1>
            <p class="mt-1 text-sm text-[#667680]">Diagnóstico seguro de SMTP, filas e remetente do PRISMA SGP</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-[#F0CACA] bg-[#FFF4F4] px-4 py-3 text-sm text-[#914747]">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl border p-5 shadow-sm {{ $configuration['ready'] ? 'border-[#BFE2D9] bg-[#EDF8F5]' : 'border-[#E8D5A7] bg-[#FFF9E9]' }}">
            <p class="text-xs font-bold uppercase tracking-wider {{ $configuration['ready'] ? 'text-[#256C5C]' : 'text-[#7A5B18]' }}">Situação do canal</p>
            <h2 class="mt-1 text-lg font-bold text-[#24313A]">{{ $configuration['ready'] ? 'Configuração apta para teste' : 'Configuração pendente' }}</h2>
            <p class="mt-2 text-sm leading-6 text-[#53636C]">As credenciais permanecem nas variáveis protegidas do ambiente. Esta tela mostra somente presença e parâmetros operacionais, nunca a senha SMTP.</p>

            @if ($configuration['issues'])
                <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-[#7A5B18]">
                    @foreach ($configuration['issues'] as $issue)<li>{{ $issue }}</li>@endforeach
                </ul>
            @endif
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @php($items = [
                ['Mailer', $configuration['mailer']],
                ['Servidor', $configuration['host']],
                ['Porta e segurança', $configuration['port'].' · '.$configuration['scheme']],
                ['Remetente', $configuration['from_name'].' <'.$configuration['from_address'].'>'],
                ['Usuário SMTP', $configuration['username_configured'] ? 'Configurado' : 'Ausente'],
                ['Senha SMTP', $configuration['password_configured'] ? 'Configurada e protegida' : 'Ausente'],
                ['Fila', $configuration['queue_connection']],
                ['Ambiente', $configuration['environment']],
            ])
            @foreach ($items as [$label, $value])
                <div class="rounded-xl border border-[#DCE3E7] bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#667680]">{{ $label }}</p>
                    <p class="mt-2 break-words text-sm font-semibold text-[#24313A]">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-2xl border border-[#C9DCE3] bg-[#F4FAFC] p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-[#24313A]">Configuração segura com Resend</h2>
            <p class="mt-2 text-sm leading-6 text-[#53636C]">
                O SGP não grava a chave SMTP no banco. Configure as variáveis abaixo no arquivo <code>.env</code>
                do ambiente local ou na área de variáveis protegidas do Laravel Cloud. Depois, limpe o cache
                da aplicação e retorne a esta tela para confirmar a situação do canal.
            </p>

            <div class="mt-4 overflow-x-auto rounded-xl border border-[#DCE3E7] bg-white">
                <table class="min-w-full divide-y divide-[#E6ECEF] text-left text-sm">
                    <thead class="bg-[#F7F9FA] text-xs uppercase tracking-wider text-[#667680]">
                        <tr>
                            <th class="px-4 py-3">Variável</th>
                            <th class="px-4 py-3">Valor para Resend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E6ECEF] text-[#24313A]">
                        @foreach ([
                            ['MAIL_MAILER', 'smtp'],
                            ['MAIL_HOST', 'smtp.resend.com'],
                            ['MAIL_PORT', '587'],
                            ['MAIL_SCHEME', 'smtp'],
                            ['MAIL_USERNAME', 'resend'],
                            ['MAIL_PASSWORD', 'chave da API do Resend'],
                            ['MAIL_FROM_ADDRESS', 'nao-responda@sgp.dev.br'],
                            ['MAIL_FROM_NAME', 'PRISMA SGP'],
                            ['QUEUE_CONNECTION', 'database'],
                        ] as [$variable, $value])
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold">{{ $variable }}</td>
                                <td class="px-4 py-3">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm leading-6 text-[#53636C]">
                <li>Verifique o domínio no Resend com os registros DNS fornecidos por ele.</li>
                <li>Cadastre as variáveis no ambiente, sem colar a chave em formulários, chamados ou capturas.</li>
                <li>Execute <code>php artisan optimize:clear</code> e reinicie o trabalhador da fila.</li>
                <li>Volte a esta tela e use o teste de entrega para um endereço sob seu controle.</li>
            </ol>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-[#24313A]">Executar teste de entrega</h2>
            <p class="mt-1 text-sm leading-6 text-[#667680]">O envio é imediato para que uma falha de conexão seja informada nesta tela. Solicitações comuns de redefinição continuam usando a fila configurada.</p>

            <form method="POST" action="{{ route('platform.communication.test') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="recipient" class="sgp-field-label">Destinatário do teste *</label>
                    <input id="recipient" name="recipient" type="email" class="sgp-input" value="{{ old('recipient', $defaultRecipient) }}" required>
                </div>
                <button class="sgp-button-primary sm:w-auto" @disabled(! $configuration['ready'])>Enviar teste</button>
            </form>

            <p class="mt-4 text-xs leading-5 text-[#667680]">A confirmação técnica significa que o provedor aceitou a entrega. A homologação final também deve verificar recebimento, pasta de spam, SPF, DKIM e DMARC no domínio utilizado.</p>
        </section>
    </div>
</x-app-layout>
