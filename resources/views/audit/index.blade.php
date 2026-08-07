<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Auditoria organizacional</h1>
            <p class="mt-1 text-sm text-[#667680]">Eventos de segurança e arquivos da organização ativa.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('audit.index') }}" class="grid gap-4 md:grid-cols-[1fr_220px_auto] md:items-end">
                <div>
                    <label for="action" class="text-sm font-semibold text-[#24313A]">Ação</label>
                    <input id="action" name="action" value="{{ $action }}" placeholder="Ex.: attachment.download" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                </div>
                <div>
                    <label for="result" class="text-sm font-semibold text-[#24313A]">Resultado</label>
                    <select id="result" name="result" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        <option value="">Todos</option>
                        <option value="success" @selected($result === 'success')>Sucesso</option>
                        <option value="denied" @selected($result === 'denied')>Negado</option>
                        <option value="failed" @selected($result === 'failed')>Falha</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="sgp-button-primary w-auto px-5">Filtrar</button>
                    @if($action !== '' || $result !== '')
                        <a href="{{ route('audit.index') }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] px-4 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="font-bold text-[#24313A]">Trilha do tenant</h2>
                <p class="mt-1 text-sm text-[#667680]">{{ $events->total() }} evento(s) encontrado(s).</p>
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
                                <th class="px-5 py-3">Recurso</th>
                                <th class="px-5 py-3">Resultado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EDF1F3]">
                            @foreach($events as $event)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 text-[#667680]">{{ $event->occurred_at->copy()->timezone($activeOrganization->timezone)->format('d/m/Y H:i:s') }}</td>
                                    <td class="px-5 py-4 font-semibold text-[#24313A]">{{ $event->action }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->actor?->name ?? 'Sistema' }}</td>
                                    <td class="px-5 py-4 text-[#667680]">{{ $event->resource_type ? $event->resource_type.' #'.$event->resource_id : 'Organização' }}</td>
                                    <td class="px-5 py-4">
                                        @php
                                            $resultClasses = match($event->result) {
                                                'success' => 'bg-[#E3F3EE] text-[#2E8B74]',
                                                'denied' => 'bg-[#FFF1D9] text-[#A86A08]',
                                                default => 'bg-[#FBE7E7] text-[#A53E3E]',
                                            };
                                            $resultLabel = match($event->result) {
                                                'success' => 'Sucesso',
                                                'denied' => 'Negado',
                                                default => 'Falha',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $resultClasses }}">{{ $resultLabel }}</span>
                                    </td>
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
