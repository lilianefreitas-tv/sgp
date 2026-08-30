<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Auditoria da plataforma</h1>
            <p class="mt-1 text-sm text-[#667680]">Eventos globais de autenticação, redefinição de senha e comunicação transacional.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="rounded-2xl border border-[#B8DDE5] bg-[#F1FAFC] p-5 shadow-sm">
            <p class="text-sm leading-6 text-[#53636C]">
                Esta trilha é exclusiva da Superadmin e não substitui a auditoria organizacional.
                Eventos de projetos, arquivos e operações de cada tenant permanecem disponíveis somente dentro da organização ativa.
            </p>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('platform.security-audit.index') }}" class="grid gap-4 md:grid-cols-[1fr_220px_auto] md:items-end">
                <div>
                    <label for="action" class="text-sm font-semibold text-[#24313A]">Ação</label>
                    <input id="action" name="action" value="{{ $action }}" placeholder="Ex.: password.platform_admin.request" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                </div>
                <div>
                    <label for="result" class="text-sm font-semibold text-[#24313A]">Resultado</label>
                    <select id="result" name="result" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        <option value="">Todos</option>
                        <option value="sent" @selected($result === 'sent')>Enviado</option>
                        <option value="success" @selected($result === 'success')>Sucesso</option>
                        <option value="failed" @selected($result === 'failed')>Falha</option>
                        <option value="throttled" @selected($result === 'throttled')>Limitado</option>
                        <option value="ignored" @selected($result === 'ignored')>Ignorado</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="sgp-button-primary w-auto px-5">Filtrar</button>
                    @if($action !== '' || $result !== '')
                        <a href="{{ route('platform.security-audit.index') }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] px-4 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="font-bold text-[#24313A]">Trilha de segurança</h2>
                <p class="mt-1 text-sm text-[#667680]">{{ $events->total() }} evento(s) encontrado(s). Segredos e credenciais não são exibidos.</p>
            </div>

            @if($events->isEmpty())
                <div class="px-6 py-14 text-center text-sm text-[#667680]">Nenhum evento encontrado.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#DCE3E7] text-sm">
                        <thead class="bg-[#F5F7F9] text-left text-xs uppercase tracking-wide text-[#667680]">
                            <tr>
                                <th class="px-5 py-3">Data</th>
                                <th class="px-5 py-3">Ação</th>
                                <th class="px-5 py-3">Ator</th>
                                <th class="px-5 py-3">Alvo</th>
                                <th class="px-5 py-3">Organização</th>
                                <th class="px-5 py-3">Ambiente</th>
                                <th class="px-5 py-3">Resultado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EDF1F3]">
                            @foreach($events as $event)
                                @php
                                    [$resultClasses, $resultLabel] = match($event->result) {
                                        'sent' => ['bg-[#E3F3EE] text-[#2E8B74]', 'Enviado'],
                                        'success' => ['bg-[#E3F3EE] text-[#2E8B74]', 'Sucesso'],
                                        'throttled' => ['bg-[#FFF1D9] text-[#A86A08]', 'Limitado'],
                                        'ignored' => ['bg-[#EEF1F3] text-[#667680]', 'Ignorado'],
                                        default => ['bg-[#FBE7E7] text-[#A53E3E]', 'Falha'],
                                    };
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 text-[#667680]">{{ $event->occurred_at->copy()->timezone(config('app.timezone', 'UTC'))->format('d/m/Y H:i:s') }}</td>
                                    <td class="px-5 py-4 font-semibold text-[#24313A]">{{ $event->action }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->actor?->name ?? 'Sistema ou usuário público' }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->targetUser?->name ?? 'Não identificado' }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->organization?->name ?? 'Plataforma' }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->environment }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $resultClasses }}">{{ $resultLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#DCE3E7] px-6 py-4">{{ $events->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
