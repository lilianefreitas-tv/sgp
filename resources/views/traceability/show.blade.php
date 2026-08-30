<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-[#8064A2]">{{ $project->code }}</p>
            <h1 class="mt-1 text-xl font-bold text-[#24313A]">Rastreabilidade e apoio ao MPS.BR</h1>
        </div>
    </x-slot>

    @php
        $summary = $matrix['summary'];
        $statusLabels = [
            'supported' => 'Com evidência',
            'partial' => 'Parcial',
            'missing' => 'Sem evidência',
            'contextual' => 'Contextual',
        ];
        $statusClasses = [
            'supported' => 'bg-[#E4F3F0] text-[#256C5C]',
            'partial' => 'bg-[#FFF4DE] text-[#9A6415]',
            'missing' => 'bg-[#FBE8E8] text-[#A23838]',
            'contextual' => 'bg-[#E6F0F8] text-[#287EA1]',
        ];
        $coverageCards = [
            ['Requisito → tarefa', $summary['requirement_work_coverage'], $summary['requirements_with_tasks'].'/'.$summary['requirements']],
            ['Requisito → teste', $summary['requirement_test_coverage'], $summary['requirements_with_tests'].'/'.$summary['requirements']],
            ['Casos executados', $summary['execution_coverage'], $summary['executed_cases'].'/'.$summary['ready_cases']],
            ['Execuções evidenciadas', $summary['evidence_coverage'], $summary['evidenced_cases'].'/'.$summary['executed_cases']],
        ];
    @endphp

    <div class="space-y-5">
        @include('requirements._project-nav')

        <section class="rounded-2xl bg-gradient-to-r from-[#123B4A] to-[#287EA1] p-7 text-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[#7FE4D0]">P08.2 · RF123</p>
                    <h2 class="mt-2 text-2xl font-bold">{{ $project->name }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-100">A matriz reúne o caminho entre origem, requisitos, trabalho, mudanças, configuração, testes, evidências e decisão formal.</p>
                </div>
                <span class="rounded-full bg-white/15 px-4 py-2 text-sm font-bold">{{ $summary['gap_count'] }} lacuna(s)</span>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($coverageCards as [$label, $value, $fraction])
                <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">{{ $label }}</p>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="text-2xl font-bold text-[#123B4A]">{{ $value === null ? 'N/A' : $value.'%' }}</p>
                        <span class="text-sm font-semibold text-[#667680]">{{ $fraction }}</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-[#E8EDF0]">
                        <div class="h-full rounded-full bg-[#2E8B74]" style="width: {{ $value ?? 0 }}%"></div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            <h2 class="font-bold text-[#24313A]">Cadeia de origem e controle</h2>
            <p class="mt-1 text-sm text-[#667680]">Os blocos representam registros existentes, não etapas obrigatórias para todos os projetos.</p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-[#BFE2D9] bg-[#F3FAF8] p-4">
                    <p class="text-xs font-bold uppercase text-[#2E8B74]">Iniciativa</p>
                    @if ($matrix['origin']['initiative'])
                        <a class="mt-2 block font-semibold text-[#256C5C] hover:underline" href="{{ route('initiatives.show', $matrix['origin']['initiative']) }}">
                            {{ $matrix['origin']['initiative']->code }}
                        </a>
                    @else
                        <p class="mt-2 text-sm text-[#667680]">Origem direta ou legada</p>
                    @endif
                </div>

                <div class="rounded-xl border border-[#BFD7DF] bg-[#F4F9FA] p-4">
                    <p class="text-xs font-bold uppercase text-[#287EA1]">Contratos</p>
                    <p class="mt-2 font-semibold text-[#123B4A]">{{ $matrix['origin']['contracts']->count() }}</p>
                    <a class="mt-1 block text-xs font-semibold text-[#287EA1] hover:underline" href="{{ route('contracts.index', ['project' => $project->id]) }}">Consultar</a>
                </div>

                <div class="rounded-xl border border-[#BFD7DF] bg-[#F4F9FA] p-4">
                    <p class="text-xs font-bold uppercase text-[#287EA1]">Baselines</p>
                    <p class="mt-2 font-semibold text-[#123B4A]">{{ $matrix['origin']['baselines']->count() }}</p>
                    <a class="mt-1 block text-xs font-semibold text-[#287EA1] hover:underline" href="{{ route('projects.baselines.index', $project) }}">Consultar</a>
                </div>

                <div class="rounded-xl border border-[#D8CCE8] bg-[#F8F5FB] p-4">
                    <p class="text-xs font-bold uppercase text-[#8064A2]">Mudanças</p>
                    <p class="mt-2 font-semibold text-[#594173]">{{ $matrix['origin']['changes']->count() }}</p>
                    <a class="mt-1 block text-xs font-semibold text-[#8064A2] hover:underline" href="{{ route('projects.change-requests.index', $project) }}">Consultar</a>
                </div>

                <div class="rounded-xl border border-[#BFE2D9] bg-[#F3FAF8] p-4">
                    <p class="text-xs font-bold uppercase text-[#2E8B74]">Homologações</p>
                    <p class="mt-2 font-semibold text-[#256C5C]">{{ $matrix['origin']['homologations']->count() }}</p>
                    <a class="mt-1 block text-xs font-semibold text-[#2E8B74] hover:underline" href="{{ route('projects.tests.index', $project) }}">Consultar</a>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="font-bold text-[#24313A]">Cobertura dos requisitos</h2>
                <p class="mt-1 text-sm text-[#667680]">Cada linha mostra a conexão com trabalho, teste, execução e evidência.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Requisito</th>
                            <th class="px-5 py-4">Tarefas</th>
                            <th class="px-5 py-4">Testes</th>
                            <th class="px-5 py-4">Resultado</th>
                            <th class="px-5 py-4">Evidências</th>
                            <th class="px-5 py-4">Lacunas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($matrix['requirements'] as $row)
                            <tr class="align-top text-sm">
                                <td class="px-5 py-4">
                                    <a class="font-bold text-[#287EA1] hover:underline" href="{{ route('projects.requirements.show', [$project, $row['requirement']]) }}">
                                        {{ $row['requirement']->code }}
                                    </a>
                                    <p class="mt-1 max-w-xs text-[#24313A]">{{ $row['requirement']->title }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-bold text-[#24313A]">{{ $row['tasks']->count() }}</span>
                                    @foreach ($row['tasks']->take(2) as $task)
                                        <a class="mt-1 block text-xs text-[#287EA1] hover:underline" href="{{ route('projects.tasks.show', [$project, $task]) }}">
                                            {{ $task->code }}
                                        </a>
                                    @endforeach
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-bold text-[#24313A]">{{ $row['test_cases']->count() }}</span>
                                    @foreach ($row['test_cases']->take(2) as $case)
                                        <a class="mt-1 block text-xs text-[#2E8B74] hover:underline" href="{{ route('projects.tests.show', [$project, $case]) }}">
                                            {{ $case->code }}
                                        </a>
                                    @endforeach
                                </td>
                                <td class="px-5 py-4">
                                    @if ($row['latest_execution'])
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['latest_execution']->result->badgeClasses() }}">
                                            {{ $row['latest_execution']->result->label() }}
                                        </span>
                                    @else
                                        <span class="text-[#667680]">Não executado</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-semibold text-[#24313A]">{{ $row['evidence_count'] }}</td>
                                <td class="px-5 py-4">
                                    @if ($row['gaps']->isEmpty())
                                        <span class="rounded-full bg-[#E4F3F0] px-2.5 py-1 text-xs font-semibold text-[#256C5C]">Coberto</span>
                                    @else
                                        @foreach ($row['gaps'] as $gap)
                                            <span class="mb-1 block text-xs font-semibold text-[#9A6415]">{{ $gap }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-[#667680]">Nenhum requisito ativo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#D8CCE8] bg-white shadow-sm">
            <div class="border-b border-[#E8E1F1] px-6 py-5">
                <h2 class="font-bold text-[#594173]">Testes e fontes verificáveis</h2>
                <p class="mt-1 text-sm text-[#667680]">Casos podem nascer de requisito, mudança ou baseline.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8E1F1]">
                    <thead class="bg-[#FBFAFD]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Caso</th>
                            <th class="px-5 py-4">Origem</th>
                            <th class="px-5 py-4">Última execução</th>
                            <th class="px-5 py-4">Evidências</th>
                            <th class="px-5 py-4">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E1F1]">
                        @forelse ($matrix['test_cases'] as $row)
                            <tr class="text-sm">
                                <td class="px-5 py-4">
                                    <a class="font-bold text-[#8064A2] hover:underline" href="{{ route('projects.tests.show', [$project, $row['case']]) }}">
                                        {{ $row['case']->code }}
                                    </a>
                                    <p class="mt-1 text-[#24313A]">{{ $row['case']->title }}</p>
                                </td>
                                <td class="px-5 py-4 text-[#667680]">{{ $row['source_label'] }}</td>
                                <td class="px-5 py-4">
                                    @if ($row['latest_execution'])
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['latest_execution']->result->badgeClasses() }}">
                                            {{ $row['latest_execution']->result->label() }}
                                        </span>
                                        <p class="mt-1 text-xs text-[#667680]">Execução {{ $row['latest_execution']->execution_number }}</p>
                                    @else
                                        <span class="text-[#667680]">Não executado</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-semibold text-[#24313A]">{{ $row['evidence_count'] }}</td>
                                <td class="px-5 py-4">
                                    @if ($row['gaps']->isEmpty())
                                        <span class="rounded-full bg-[#E4F3F0] px-2.5 py-1 text-xs font-semibold text-[#256C5C]">Coberto</span>
                                    @else
                                        @foreach ($row['gaps'] as $gap)
                                            <span class="mb-1 block text-xs font-semibold text-[#9A6415]">{{ $gap }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-[#667680]">Nenhum caso de teste registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#BFD7DF] bg-white shadow-sm">
            <div class="border-b border-[#DCE8EC] px-6 py-5">
                <h2 class="font-bold text-[#123B4A]">Matriz de apoio ao MPS.BR</h2>
                <p class="mt-1 text-sm text-[#667680]">Leitura operacional do projeto, sem declaração automática de conformidade ou maturidade.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F4F9FA]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Processo</th>
                            <th class="px-5 py-4">Capacidade apoiada</th>
                            <th class="px-5 py-4">Evidência encontrada</th>
                            <th class="px-5 py-4">Situação</th>
                            <th class="px-5 py-4">Limite ou lacuna</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @foreach ($matrix['mps'] as $process)
                            <tr class="align-top text-sm">
                                <td class="px-5 py-4">
                                    <span class="font-bold text-[#287EA1]">{{ $process['code'] }}</span>
                                    <p class="mt-1 font-semibold text-[#24313A]">{{ $process['name'] }}</p>
                                </td>
                                <td class="max-w-xs px-5 py-4 text-[#24313A]">{{ $process['capacity'] }}</td>
                                <td class="max-w-xs px-5 py-4 text-[#667680]">{{ $process['evidence'] }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$process['status']] }}">
                                        {{ $statusLabels[$process['status']] }}
                                    </span>
                                </td>
                                <td class="max-w-sm px-5 py-4 text-[#667680]">{{ $process['gap'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#DCE8EC] bg-[#F4F9FA] px-6 py-5 text-sm leading-6 text-[#52636D]">
                <strong class="text-[#123B4A]">Limite institucional:</strong>
                o PRISMA SGP apoia a operacionalização de processos, a rastreabilidade e a produção de evidências úteis. O uso do sistema não certifica a organização, não atribui nível de maturidade e não substitui implementação ou avaliação autorizada.
            </div>
        </section>
    </div>
</x-app-layout>
