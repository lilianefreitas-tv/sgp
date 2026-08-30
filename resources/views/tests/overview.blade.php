<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold text-[#2E8B74]">Qualidade verificável</p><h1 class="mt-1 text-xl font-bold text-[#24313A]">Testes e homologações</h1></div></x-slot>
    <div class="space-y-5">
        <section class="rounded-2xl bg-gradient-to-r from-[#123B4A] to-[#287EA1] p-7 text-white shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#7FE4D0]">P08.1 · Gestão da qualidade</p>
            <h2 class="mt-2 text-2xl font-bold">Da necessidade à evidência</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-100">Planeje casos, execute verificações e preserve resultados antes de registrar uma decisão formal de homologação.</p>
        </section>
        <section class="space-y-4">
            @forelse($projects as $project)
                @php
                    $ready = $project->testCases->where('status', \App\Enums\TestCaseStatus::Ready);
                    $passed = $ready->filter(fn($case) => $case->latestExecution()?->result === \App\Enums\TestExecutionResult::Passed)->count();
                @endphp
                <a href="{{ route('projects.tests.index', $project) }}" class="flex items-center justify-between gap-5 rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm transition hover:border-[#2E8B74] hover:bg-[#F7FBFA]">
                    <div><p class="text-xs font-semibold text-[#287EA1]">{{ $project->code }}</p><h3 class="mt-1 font-bold text-[#24313A]">{{ $project->name }}</h3><p class="mt-1 text-sm text-[#667680]">{{ $project->test_cases_count }} casos · {{ $passed }}/{{ $ready->count() }} prontos com resultado aprovado · {{ $project->homologations_count }} decisões</p></div>
                    <span class="text-xl text-[#2E8B74]">→</span>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-[#B8C5CB] bg-white px-6 py-12 text-center text-sm text-[#667680]">Nenhum projeto acessível para gestão de testes.</div>
            @endforelse
        </section>
    </div>
</x-app-layout>
