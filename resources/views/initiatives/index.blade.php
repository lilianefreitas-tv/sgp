<x-app-layout>
    <x-slot name="header">Iniciativas</x-slot>

    <div class="space-y-4">
        @forelse ($initiatives as $initiative)
            @php($status = $availability[$initiative->id])
            <article class="rounded-xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-[#667680]">{{ $initiative->code }} · {{ $initiative->origin->label() }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-[#24313A]">{{ $initiative->title }}</h2>
                        <p class="mt-2 text-sm text-[#667680]">{{ $status['reason'] }}</p>
                    </div>
                    @if ($initiative->project)
                        <a class="sgp-link" href="{{ route('projects.show', $initiative->project) }}">Abrir projeto</a>
                    @elseif ($status['available'])
                        <a class="sgp-button-primary" href="{{ route('initiatives.conversion.show', $initiative) }}">Iniciar projeto</a>
                    @else
                        <span class="text-sm font-semibold text-[#667680]">Indisponível</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-[#BFCAD0] p-6 text-[#667680]">Não há iniciativas neste contexto.</p>
        @endforelse
    </div>
</x-app-layout>
