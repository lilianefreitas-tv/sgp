<x-app-layout>
    <x-slot name="header">Pendências documentais</x-slot>

    <div class="mx-auto max-w-6xl space-y-6 py-8">
        <section class="sgp-page-intro">
            <p class="sgp-page-kicker">Fluxo documental</p>
            <h1 class="mt-2 text-3xl font-bold">Minhas pendências</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">Correções, revisões, aprovações e publicações que aguardam uma ação sua.</p>
        </section>

        <section class="space-y-3">
            @forelse ($artifacts as $artifact)
                <a href="{{ route('artifacts.show', $artifact) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md">
                    <div>
                        <p class="text-sm text-slate-500">{{ $artifact->code }} · {{ $artifact->type->label() }}</p>
                        <h2 class="mt-1 font-semibold text-slate-900">{{ $artifact->title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $artifact->initiative?->title ?? $artifact->project?->name }}</p>
                    </div>
                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-sm font-semibold text-cyan-800">{{ $artifact->workflow_state->label() }}</span>
                </a>
            @empty
                <div class="sgp-empty-state">
                    <div class="sgp-empty-icon"><span class="text-lg">✓</span></div>
                    <h2 class="mt-4 font-bold text-[#24313A]">Nenhuma ação documental pendente</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#667680]">Quando um documento precisar de correção, revisão, aprovação ou publicação por você, ele aparecerá nesta área.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
