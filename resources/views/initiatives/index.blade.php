<x-app-layout>
    <x-slot name="header">Iniciativas</x-slot>

    <div class="space-y-5">
        @if (session('success')) <div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div> @endif

        <section class="relative overflow-hidden rounded-2xl bg-[#154C5D] px-6 py-7 text-white shadow-sm sm:px-8 sm:py-8">
            <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full border border-white/10"></div>
            <div class="absolute -right-5 top-5 h-32 w-32 rounded-full border border-white/10"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#78E1D1]">Porta de entrada</p>
                    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Iniciativas</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-white/80 sm:text-base">
                        Registre uma necessidade, acompanhe sua evolução comercial ou operacional e converta-a em projeto quando estiver pronta.
                    </p>
                </div>
                <a class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-bold text-[#123F4D] shadow-sm transition hover:bg-[#F1F6F7] focus:outline-none focus:ring-2 focus:ring-white/70" href="{{ route('initiatives.create') }}">
                    Nova iniciativa
                </a>
            </div>
        </section>
        <nav class="flex flex-wrap gap-2" aria-label="Filtros das iniciativas">
            @foreach (['active' => 'Ativas', 'converted' => 'Convertidas', 'cancelled' => 'Canceladas', 'archived' => 'Arquivadas', 'all' => 'Todas'] as $key => $label)
                <a href="{{ route('initiatives.index', ['status' => $key]) }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ $filter === $key ? 'border-[#154C5D] bg-[#154C5D] text-white' : 'border-[#DCE3E7] bg-white text-[#41515B] hover:bg-[#F1F6F7]' }}">{{ $label }}</a>
            @endforeach
        </nav>
        @forelse ($initiatives as $initiative)
            @php($status = $availability[$initiative->id])
            <article class="rounded-xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                    <div>
                        <p class="text-sm text-[#667680]">{{ $initiative->code }} · {{ $initiative->origin->label() }} · {{ $initiative->state->label() }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-[#24313A]"><a class="hover:text-[#176B7D]" href="{{ route('initiatives.show', $initiative) }}">{{ $initiative->title }}</a></h2>
                        <p class="mt-2 text-sm text-[#667680]">{{ $status['reason'] }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                        <a class="sgp-link" href="{{ route('initiatives.show', $initiative) }}">Gerenciar</a>
                        <a class="sgp-link" href="{{ route('initiatives.artifacts.index', $initiative) }}">Documentos</a>
                        @if ($initiative->origin === \App\Enums\InitiativeOrigin::Commercial)
                            <a class="sgp-link" href="{{ route('commercial.show', $initiative) }}">Jornada comercial</a>
                        @endif
                        @if ($initiative->project)
                            <a class="sgp-link" href="{{ route('projects.show', $initiative->project) }}">Abrir projeto</a>
                        @elseif ($status['available'])
                            <a class="inline-flex items-center justify-center rounded-lg bg-[#154C5D] px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1D6073] focus:outline-none focus:ring-2 focus:ring-[#2A8298]/40" href="{{ route('initiatives.conversion.show', $initiative) }}">Iniciar projeto</a>
                        @else
                            <span class="text-sm font-semibold text-[#667680]">Indisponível</span>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-[#BFCAD0] p-6 text-[#667680]">Não há iniciativas neste contexto.</p>
        @endforelse
    </div>
</x-app-layout>
