<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold text-[#8064A2]">Cobertura ponta a ponta</p><h1 class="mt-1 text-xl font-bold text-[#24313A]">Rastreabilidade</h1></div></x-slot>
    <div class="space-y-5">
        <section class="rounded-2xl bg-gradient-to-r from-[#123B4A] to-[#287EA1] p-7 text-white shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#7FE4D0]">P08.2 · Qualidade e governança</p>
            <h2 class="mt-2 text-2xl font-bold">Do requisito à homologação</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-100">Acompanhe vínculos, cobertura e lacunas usando os registros operacionais já existentes. A matriz não duplica dados e respeita o acesso de cada projeto.</p>
        </section>

        <section class="space-y-4">
            @forelse($projects as $item)
                @php($project = $item['project'])
                @php($summary = $item['summary'])
                <a href="{{ route('projects.traceability.show', $project) }}" class="block rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm transition hover:border-[#8064A2] hover:bg-[#FBFAFD]">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><p class="text-xs font-semibold text-[#287EA1]">{{ $project->code }}</p><h3 class="mt-1 font-bold text-[#24313A]">{{ $project->name }}</h3><p class="mt-1 text-sm text-[#667680]">{{ $summary['requirements'] }} requisito(s) · {{ $summary['ready_cases'] }} caso(s) pronto(s) · {{ $summary['homologations'] }} homologação(ões)</p></div>
                        <div class="flex items-center gap-3"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $summary['gap_count'] === 0 ? 'bg-[#E4F3F0] text-[#256C5C]' : 'bg-[#FFF4DE] text-[#9A6415]' }}">{{ $summary['gap_count'] }} lacuna(s)</span><span class="text-xl text-[#8064A2]">→</span></div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach([
                            ['Trabalho', $summary['requirement_work_coverage']],
                            ['Testes', $summary['requirement_test_coverage']],
                            ['Execução', $summary['execution_coverage']],
                            ['Evidências', $summary['evidence_coverage']],
                        ] as [$label, $value])
                            <div class="rounded-xl bg-[#F6F8F9] px-4 py-3"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">{{ $label }}</p><p class="mt-1 text-lg font-bold text-[#123B4A]">{{ $value === null ? 'N/A' : $value.'%' }}</p></div>
                        @endforeach
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-[#B8C5CB] bg-white px-6 py-12 text-center text-sm text-[#667680]">Nenhum projeto acessível para rastreabilidade.</div>
            @endforelse
        </section>
    </div>
</x-app-layout>
