<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Histórico consolidado</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @include('requirements._project-nav')

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('projects.history.index', $project) }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label for="type" class="text-sm font-semibold text-[#24313A]">Filtrar eventos</label>
                    <select id="type" name="type" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        @foreach($filters as $value => $label)
                            <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="sgp-button-primary w-auto px-5">Aplicar filtro</button>
                @if($filter !== '')<a href="{{ route('projects.history.index', $project) }}" class="inline-flex rounded-lg border border-[#DCE3E7] px-4 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar</a>@endif
            </form>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-[#24313A]">Linha do tempo do projeto</h2>
                        <p class="mt-1 text-sm text-[#667680]">Eventos relevantes reunidos em ordem cronológica decrescente.</p>
                    </div>
                    <span class="rounded-full bg-[#E8F3F6] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $events->total() }} evento(s)</span>
                </div>
            </div>

            @if($events->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-semibold text-[#24313A]">Nenhum evento encontrado</p>
                    <p class="mt-1 text-sm text-[#667680]">Altere o filtro para consultar outras categorias.</p>
                </div>
            @else
                <div class="px-6 py-2">
                    @php
                        $toneClasses = [
                            'blue' => 'bg-[#DDEFF5] text-[#1D5D73]',
                            'purple' => 'bg-[#EEE8F7] text-[#694A8B]',
                            'green' => 'bg-[#E3F3EE] text-[#2E8B74]',
                            'amber' => 'bg-[#FFF1D9] text-[#A86A08]',
                            'cyan' => 'bg-[#E1F5F4] text-[#247A76]',
                            'slate' => 'bg-[#EDF1F3] text-[#53636C]',
                            'red' => 'bg-[#FBE7E7] text-[#A53E3E]',
                        ];
                    @endphp
                    @foreach($events as $event)
                        <article class="relative border-l-2 border-[#DCE3E7] py-5 pl-7">
                            <span class="absolute -left-[9px] top-6 h-4 w-4 rounded-full border-4 border-white bg-[#5FC4B6]"></span>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide {{ $toneClasses[$event['tone']] ?? $toneClasses['slate'] }}">{{ $filters[$event['category']] ?? 'Evento' }}</span>
                                        <h3 class="font-semibold text-[#24313A]">{{ $event['title'] }}</h3>
                                    </div>
                                    @if($event['description'])<p class="mt-2 text-sm leading-6 text-[#667680]">{{ $event['description'] }}</p>@endif
                                    <p class="mt-2 text-xs text-[#82919A]">Responsável: {{ $event['actor'] }}</p>
                                </div>
                                <time class="shrink-0 text-xs font-medium text-[#667680]" datetime="{{ $event['occurred_at']->toIso8601String() }}">{{ $event['occurred_at']->format('d/m/Y H:i') }}</time>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="border-t border-[#DCE3E7] px-6 py-4">{{ $events->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
