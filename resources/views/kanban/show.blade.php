<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</p>
            <h1 class="truncate text-xl font-bold text-[#24313A]">Kanban de {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-[#667680]">Acompanhe e movimente as tarefas pelas etapas do fluxo de trabalho.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-[#F0CACA] bg-[#FFF3F3] px-4 py-3 text-sm text-[#A33D3D]">
                <p class="font-semibold">Revise os campos informados.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('requirements._project-nav')

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(150px,1fr))_auto_auto] xl:items-end">
            <div>
                <label for="responsible" class="sgp-field-label">Responsável</label>
                <select id="responsible" name="responsible" class="sgp-input">
                    <option value="">Todos</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected($filters['responsible'] === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="priority" class="sgp-field-label">Prioridade</label>
                <select id="priority" name="priority" class="sgp-input">
                    <option value="">Todas</option>
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="requirement" class="sgp-field-label">Requisito</label>
                <select id="requirement" name="requirement" class="sgp-input">
                    <option value="">Todos</option>
                    @foreach ($requirements as $requirement)
                        <option value="{{ $requirement->id }}" @selected($filters['requirement'] === (string) $requirement->id)>{{ $requirement->code }} · {{ $requirement->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="situation" class="sgp-field-label">Prazo</label>
                <select id="situation" name="situation" class="sgp-input">
                    <option value="">Todos</option>
                    <option value="overdue" @selected($filters['situation'] === 'overdue')>Atrasadas</option>
                    <option value="on_time" @selected($filters['situation'] === 'on_time')>Com prazo vigente</option>
                    <option value="without_due_date" @selected($filters['situation'] === 'without_due_date')>Sem prazo</option>
                </select>
            </div>

            <button class="inline-flex items-center justify-center whitespace-nowrap rounded-lg bg-[#E6F0F3] px-4 py-3 text-sm font-semibold text-[#123B4A] hover:bg-[#D8E8ED]">Filtrar</button>
            <a href="{{ route('projects.kanban.show', $project) }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-[#DCE3E7] px-4 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar filtros</a>
        </form>

        <div
            class="sgp-kanban-board"
            data-kanban-board
            data-can-move="{{ $canMove ? '1' : '0' }}"
        >
            @foreach ($board->columns as $column)
                @php
                    $columnTasks = $tasks->get($column->status->value, collect());
                @endphp
                <section class="sgp-kanban-column" data-kanban-column="{{ $column->status->value }}">
                    <header class="sgp-kanban-column-header">
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-bold text-[#24313A]">{{ $column->name }}</h2>
                            <p class="mt-0.5 text-xs text-[#667680]">{{ $column->status->label() }}</p>
                        </div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[#123B4A] shadow-sm" data-kanban-count>{{ $columnTasks->count() }}</span>
                    </header>

                    <div class="sgp-kanban-dropzone" data-kanban-dropzone="{{ $column->status->value }}">
                        @if ($columnTasks->isNotEmpty())
                            @foreach ($columnTasks as $task)
                            @php
                                $isOverdue = $task->due_date
                                    && $task->due_date->isBefore(today())
                                    && $task->status !== \App\Enums\TaskStatus::Completed;
                            @endphp

                            <article
                                class="sgp-kanban-card {{ $isOverdue ? 'sgp-kanban-card-overdue' : '' }}"
                                data-kanban-card
                                data-move-url="{{ route('projects.kanban.tasks.move', [$project, $task]) }}"
                                data-task-id="{{ $task->id }}"
                                draggable="{{ $canMove ? 'true' : 'false' }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wide text-[#287EA1]">{{ $task->code }}</span>
                                    <span class="rounded-full px-2 py-1 text-[11px] font-bold {{ $task->priority->badgeClasses() }}">{{ $task->priority->label() }}</span>
                                </div>

                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="mt-2 block text-sm font-bold leading-5 text-[#24313A] hover:text-[#287EA1]">
                                    {{ $task->title }}
                                </a>

                                <div class="mt-3 space-y-2 text-xs text-[#667680]">
                                    <p class="flex items-center gap-2">
                                        <svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 9a7 7 0 0 0-14 0" />
                                        </svg>
                                        <span class="truncate">{{ $task->responsible?->name ?? 'Sem responsável' }}</span>
                                    </p>

                                    @if ($task->requirement)
                                        <p class="flex items-center gap-2">
                                            <svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6M9 16h6M8 3h8l4 4v14H4V3h4Zm8 0v5h5" />
                                            </svg>
                                            <span class="truncate">{{ $task->requirement->code }} · {{ $task->requirement->title }}</span>
                                        </p>
                                    @endif

                                    <p class="flex items-center gap-2 {{ $isOverdue ? 'font-bold text-[#C44B4B]' : '' }}">
                                        <svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
                                        </svg>
                                        <span>{{ $task->due_date?->format('d/m/Y') ?? 'Sem prazo' }}{{ $isOverdue ? ' · Atrasada' : '' }}</span>
                                    </p>
                                </div>

                                @if ($canMove)
                                    <form method="POST" action="{{ route('projects.kanban.tasks.move', [$project, $task]) }}" class="mt-4 border-t border-[#E8EDF0] pt-3" data-kanban-fallback>
                                        @csrf
                                        @method('PATCH')
                                        <label for="move-task-{{ $task->id }}" class="sr-only">Mover {{ $task->code }} para</label>
                                        <select id="move-task-{{ $task->id }}" name="status" class="block w-full rounded-lg border-[#DCE3E7] py-2 pl-3 pr-8 text-xs text-[#667680]" onchange="this.form.submit()">
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($task->status->value === $value)>Mover para: {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </article>
                            @endforeach
                        @else
                            <div class="sgp-kanban-empty" data-kanban-empty>
                                <p>Nenhuma tarefa</p>
                                @if ($canMove)
                                    <span>Arraste um card para esta coluna.</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <p class="text-xs text-[#667680]">
            @if ($canMove)
                Arraste os cards entre as colunas ou utilize o seletor “Mover para”. Cada mudança atualiza o status e registra o histórico da tarefa.
            @else
                Você possui acesso de consulta. A movimentação depende de um papel de gestão, análise ou desenvolvimento no projeto.
            @endif
        </p>

        @if ($canConfigure)
            <details class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
                <summary class="cursor-pointer px-5 py-4 text-sm font-bold text-[#24313A]">Configurar nomes e ordem das colunas</summary>
                <form method="POST" action="{{ route('projects.kanban.columns.update', $project) }}" class="border-t border-[#E8EDF0] p-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($board->columns as $index => $column)
                            <div class="rounded-xl border border-[#DCE3E7] p-4">
                                <input type="hidden" name="columns[{{ $index }}][status]" value="{{ $column->status->value }}">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-[#287EA1]">{{ $column->status->label() }}</p>
                                <div class="grid grid-cols-[1fr_90px] gap-3">
                                    <div>
                                        <label for="column-name-{{ $column->id }}" class="sgp-field-label">Nome</label>
                                        <input id="column-name-{{ $column->id }}" name="columns[{{ $index }}][name]" class="sgp-input" value="{{ old("columns.{$index}.name", $column->name) }}" required maxlength="100">
                                    </div>
                                    <div>
                                        <label for="column-position-{{ $column->id }}" class="sgp-field-label">Ordem</label>
                                        <select id="column-position-{{ $column->id }}" name="columns[{{ $index }}][position]" class="sgp-input">
                                            @foreach (range(1, $board->columns->count()) as $position)
                                                <option value="{{ $position }}" @selected((int) old("columns.{$index}.position", $column->position) === $position)>{{ $position }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button class="sgp-button-primary w-auto">Salvar configuração</button>
                    </div>
                </form>
            </details>
        @endif
    </div>
</x-app-layout>
