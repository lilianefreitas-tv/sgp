<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Cronograma</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @include('requirements._project-nav', ['project' => $project])

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-bold text-[#24313A]">Gantt básico do projeto</h2>
                    <p class="mt-1 text-sm text-[#667680]">Visualização das tarefas planejadas, sem dependências ou caminho crítico</p>
                </div>
                <div class="rounded-lg bg-[#F0F4F6] px-3 py-2 text-xs font-semibold text-[#52616A]">
                    {{ $timelineStart->format('d/m/Y') }} a {{ $timelineEnd->format('d/m/Y') }}
                </div>
            </div>
        </section>

        <section class="overflow-x-auto rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="min-w-[980px]">
                <div class="grid grid-cols-[320px_1fr] border-b border-[#DCE3E7] bg-[#F7F9FA]">
                    <div class="border-r border-[#DCE3E7] px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#667680]">Requisito / tarefa</div>
                    <div class="relative h-12">
                        @foreach ($monthMarkers as $marker)
                            <div class="absolute inset-y-0 flex items-center justify-center border-r border-[#DCE3E7] text-xs font-bold text-[#667680]" style="left: {{ $marker['left'] }}%; width: {{ $marker['width'] }}%;">
                                {{ $marker['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>

                @forelse ($groups as $groupKey => $tasks)
                    @php($requirement = $tasks->first()->requirement)
                    <div class="grid grid-cols-[320px_1fr] border-b border-[#DCE3E7] bg-[#F8FAFB]">
                        <div class="border-r border-[#DCE3E7] px-4 py-2.5 text-xs font-bold text-[#24313A]">
                            {{ $requirement ? $requirement->code.' · '.$requirement->title : 'Tarefas sem requisito vinculado' }}
                        </div>
                        <div></div>
                    </div>
                    @foreach ($tasks as $task)
                        <div class="grid grid-cols-[320px_1fr] border-b border-[#E8EDF0] last:border-b-0">
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="border-r border-[#E8EDF0] px-4 py-3 hover:bg-[#F8FAFB]">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-[#24313A]">{{ $task->code }} · {{ $task->title }}</p>
                                    <span class="flex-none rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>
                                </div>
                                <p class="mt-1 text-xs text-[#667680]">{{ $task->responsible?->name ?? 'Sem responsável' }} · {{ ($task->start_date ?? $task->due_date)->format('d/m') }} a {{ ($task->due_date ?? $task->start_date)->format('d/m') }}</p>
                            </a>
                            <div class="relative min-h-16 overflow-hidden bg-[linear-gradient(to_right,#EEF2F4_1px,transparent_1px)] bg-[size:7.142857%_100%]">
                                @if (today()->between($timelineStart, $timelineEnd))
                                    <div class="absolute inset-y-0 z-10 w-px bg-[#C44B4B]/60" style="left: {{ ($timelineStart->diffInDays(today()) / $totalDays) * 100 }}%;" title="Hoje"></div>
                                @endif
                                <div class="absolute top-1/2 h-7 -translate-y-1/2 overflow-hidden rounded-lg {{ $task->status === \App\Enums\TaskStatus::Completed ? 'bg-[#2E8B74]' : (($task->due_date?->lt(today())) ? 'bg-[#C44B4B]' : 'bg-[#287EA1]') }}" style="left: {{ $task->gantt_left }}%; width: {{ $task->gantt_width }}%;" title="{{ $task->code }} · {{ $task->title }}">
                                    <div class="flex h-full items-center px-2 text-[10px] font-bold text-white">{{ $task->code }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <div class="px-6 py-12 text-center text-sm text-[#667680]">Nenhuma tarefa com data cadastrada para exibir no Gantt.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <h2 class="font-bold text-[#24313A]">Tarefas sem datas</h2>
            <p class="mt-1 text-sm text-[#667680]">Itens que precisam de início ou prazo para aparecer no cronograma</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse ($unplannedTasks as $task)
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" class="rounded-xl border border-[#DCE3E7] p-3 hover:border-[#287EA1]">
                        <p class="text-xs font-semibold text-[#287EA1]">{{ $task->code }}</p>
                        <p class="mt-1 text-sm font-bold text-[#24313A]">{{ $task->title }}</p>
                    </a>
                @empty
                    <p class="text-sm text-[#667680]">Todas as tarefas possuem alguma data de planejamento.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
