<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-bold text-[#24313A]">Jornada comercial</h1><p class="mt-1 text-sm text-[#667680]">Da oportunidade identificada à decisão comercial</p></div></x-slot>
    @php
        $withOpportunity = $initiatives->filter(fn ($item) => $item->opportunity !== null);
        $won = $withOpportunity->filter(fn ($item) => $item->opportunity->state === 'won')->count();
        $inProgress = $withOpportunity->filter(fn ($item) => ! in_array($item->opportunity->state, ['won', 'lost'], true))->count();
    @endphp
    <div class="space-y-5">
        <section class="sgp-page-intro"><p class="sgp-page-kicker">Pipeline comercial</p><div class="mt-2 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><h2 class="text-2xl font-bold">Conduza oportunidades com histórico e rastreabilidade</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">Centralize levantamentos, propostas, negociações e decisões antes da conversão em projeto.</p></div><a class="sgp-button-primary bg-white !text-[#123B4A] hover:!bg-[#EDF6F8] sm:w-auto" href="{{ route('initiatives.create') }}">Nova iniciativa comercial</a></div></section>
        <section class="sgp-stat-grid">
            <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Iniciativas comerciais</p><p class="sgp-stat-value">{{ $initiatives->count() }}</p></div>
            <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Com oportunidade</p><p class="sgp-stat-value">{{ $withOpportunity->count() }}</p></div>
            <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Em andamento</p><p class="sgp-stat-value">{{ $inProgress }}</p></div>
            <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Vencidas</p><p class="sgp-stat-value">{{ $won }}</p></div>
        </section>
        <section class="sgp-card overflow-hidden">
            <div class="border-b border-[#E8EDF0] px-5 py-4"><h2 class="sgp-section-heading">Oportunidades e iniciativas</h2><p class="sgp-section-description">Abra um registro para continuar a jornada.</p></div>
            <div class="divide-y divide-[#E8EDF0]">
                @forelse($initiatives as $initiative)
                    @php($state = $initiative->opportunity?->state)
                    @php($stateLabels = ['open' => 'Aberta', 'identified' => 'Identificada', 'qualified' => 'Qualificada', 'under_discovery' => 'Em levantamento', 'under_proposal' => 'Em proposta', 'under_negotiation' => 'Em negociação', 'won' => 'Vencida', 'lost' => 'Perdida', 'suspended' => 'Suspensa'])
                    <a class="grid gap-4 px-5 py-5 transition hover:bg-[#FBFCFD] md:grid-cols-[1fr_auto] md:items-center" href="{{ route('commercial.show', $initiative) }}">
                        <div><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold text-[#287EA1]">{{ $initiative->code }}</span><span class="sgp-badge {{ $state === 'won' ? 'sgp-badge-success' : ($state === 'lost' ? 'sgp-badge-danger' : 'sgp-badge-info') }}">{{ $stateLabels[$state] ?? 'Sem oportunidade' }}</span></div><h3 class="mt-2 font-bold text-[#24313A]">{{ $initiative->title }}</h3><p class="mt-1 text-sm text-[#667680]">{{ $initiative->opportunity?->code ?? 'Cadastre a oportunidade para iniciar o acompanhamento comercial.' }}</p></div>
                        <span class="sgp-action-link">Abrir jornada <span class="ml-2">→</span></span>
                    </a>
                @empty
                    <div class="sgp-empty-state m-5"><div class="sgp-empty-icon"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 19V9m6 10V5m6 14v-7m4 7H2" /></svg></div><h3 class="mt-4 font-bold text-[#24313A]">Nenhuma iniciativa comercial</h3><p class="mt-2 max-w-md text-sm text-[#667680]">Crie uma iniciativa com origem Comercial para começar a jornada.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
