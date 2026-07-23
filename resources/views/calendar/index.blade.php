<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">{{ $selectedProject ? 'Calendário do projeto' : 'Calendário geral' }}</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $selectedProject ? $selectedProject->code.' · '.$selectedProject->name : 'Projetos e tarefas organizados por data' }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if ($selectedProject)
            @include('requirements._project-nav', ['project' => $selectedProject])
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-4 shadow-sm">
            <form method="GET" action="{{ $selectedProject ? route('projects.calendar.index', $selectedProject) : route('calendar.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="month" class="mb-1 block text-xs font-semibold text-[#52616A]">Mês</label>
                    <input id="month" name="month" type="month" value="{{ $month->format('Y-m') }}" class="rounded-lg border-[#CBD5DA] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                </div>
                @unless ($selectedProject)
                    <div class="min-w-64 flex-1">
                        <label for="project" class="mb-1 block text-xs font-semibold text-[#52616A]">Projeto</label>
                        <select id="project" name="project" class="w-full rounded-lg border-[#CBD5DA] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                            <option value="">Todos os projetos</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->code }} · {{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div class="min-w-48">
                    <label for="responsible" class="mb-1 block text-xs font-semibold text-[#52616A]">Responsável</label>
                    <select id="responsible" name="responsible" class="w-full rounded-lg border-[#CBD5DA] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        <option value="">Todos</option>
                        @foreach ($responsibles as $responsible)
                            <option value="{{ $responsible->id }}" @selected(request('responsible') == $responsible->id)>{{ $responsible->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-44">
                    <label for="status" class="mb-1 block text-xs font-semibold text-[#52616A]">Situação da tarefa</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#CBD5DA] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        <option value="">Todas</option>
                        @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-44">
                    <label for="type" class="mb-1 block text-xs font-semibold text-[#52616A]">Tipo de evento</label>
                    <select id="type" name="type" class="w-full rounded-lg border-[#CBD5DA] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">
                        <option value="">Todos</option>
                        @foreach (['project_start' => 'Início de projeto', 'project_due' => 'Entrega de projeto', 'task_start' => 'Início de tarefa', 'task_due' => 'Prazo de tarefa', 'task_completed' => 'Conclusão de tarefa'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-lg bg-[#123B4A] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1D5D73]">Aplicar filtros</button>
                <a href="{{ $selectedProject ? route('projects.calendar.index', $selectedProject) : route('calendar.index') }}" class="rounded-lg border border-[#CBD5DA] px-4 py-2.5 text-sm font-semibold text-[#52616A] hover:bg-[#F3F6F7]">Hoje</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#DCE3E7] px-5 py-4">
                <a href="{{ request()->fullUrlWithQuery(['month' => $month->subMonth()->format('Y-m')]) }}" class="rounded-lg border border-[#DCE3E7] px-3 py-2 text-sm font-semibold text-[#52616A] hover:bg-[#F3F6F7]">← Anterior</a>
                <h2 class="text-lg font-bold capitalize text-[#24313A]">{{ $month->locale('pt_BR')->translatedFormat('F \d\e Y') }}</h2>
                <a href="{{ request()->fullUrlWithQuery(['month' => $month->addMonth()->format('Y-m')]) }}" class="rounded-lg border border-[#DCE3E7] px-3 py-2 text-sm font-semibold text-[#52616A] hover:bg-[#F3F6F7]">Próximo →</a>
            </div>
            <div class="grid grid-cols-7 border-b border-[#DCE3E7] bg-[#F7F9FA]">
                @foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
                    <div class="px-2 py-2.5 text-center text-xs font-bold uppercase tracking-wide text-[#667680]">{{ $weekday }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    <div class="min-h-32 border-b border-r border-[#E8EDF0] p-2 {{ $day['date']->month !== $month->month ? 'bg-[#FAFBFC]' : 'bg-white' }}">
                        <div class="mb-1 flex justify-end">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $day['date']->isToday() ? 'bg-[#123B4A] text-white' : ($day['date']->month !== $month->month ? 'text-[#AAB5BB]' : 'text-[#52616A]') }}">{{ $day['date']->day }}</span>
                        </div>
                        <div class="space-y-1">
                            @foreach ($day['events']->take(3) as $event)
                                <a href="{{ $event['url'] }}" title="{{ $event['title'] }}" class="block truncate rounded px-1.5 py-1 text-[11px] font-semibold {{ $event['classes'] }}">{{ $event['label'] }}</a>
                            @endforeach
                            @if ($day['events']->count() > 3)
                                <p class="px-1 text-[11px] font-semibold text-[#667680]">+ {{ $day['events']->count() - 3 }} evento(s)</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <h2 class="font-bold text-[#24313A]">Itens sem planejamento</h2>
            <p class="mt-1 text-sm text-[#667680]">Tarefas abertas que ainda não possuem data de início nem prazo</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse ($unplannedTasks as $task)
                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="rounded-xl border border-[#DCE3E7] p-3 hover:border-[#287EA1]">
                        <p class="text-xs font-semibold text-[#287EA1]">{{ $task->project->code }} · {{ $task->code }}</p>
                        <p class="mt-1 text-sm font-bold text-[#24313A]">{{ $task->title }}</p>
                    </a>
                @empty
                    <p class="text-sm text-[#667680]">Nenhuma tarefa sem planejamento encontrada.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
