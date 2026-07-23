<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-[#24313A]">Painel</h1>
                <p class="mt-1 text-sm text-[#667680]">Visão executiva dos projetos disponíveis para você</p>
            </div>
            <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#123B4A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1D5D73]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" /></svg>
                Abrir calendário
            </a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="relative overflow-hidden rounded-2xl px-6 py-5 text-white shadow-sm sm:px-8" style="background: linear-gradient(135deg, #123B4A 0%, #1D5D73 100%);">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-[#A8E2D7]">Bem-vinda ao SGP</p>
                <h2 class="mt-2 text-2xl font-bold text-white">Olá, {{ Auth::user()->name }}!</h2>
                <p class="mt-2 text-sm leading-6 text-[#E4EEF1]">Os indicadores abaixo refletem os projetos, requisitos, tarefas, prazos e documentos registrados no sistema.</p>
            </div>
            <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full border border-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-24 right-24 h-52 w-52 rounded-full border border-white/10" aria-hidden="true"></div>
        </section>

        @php
            $cards = [
                ['label' => 'Projetos ativos', 'value' => $activeProjectsCount, 'detail' => 'disponíveis para acompanhamento', 'tone' => 'bg-[#E6F0F3] text-[#123B4A]'],
                ['label' => 'Projetos atrasados', 'value' => $delayedProjectsCount, 'detail' => 'com entrega prevista vencida', 'tone' => 'bg-[#FBE8E8] text-[#C44B4B]'],
                ['label' => 'Requisitos', 'value' => $requirementsCount, 'detail' => 'requisitos ativos cadastrados', 'tone' => 'bg-[#E8F1FA] text-[#287EA1]'],
                ['label' => 'Aguardando análise', 'value' => $pendingRequirementsCount, 'detail' => 'propostos ou em análise', 'tone' => 'bg-[#FFF3DE] text-[#D89427]'],
                ['label' => 'Tarefas pendentes', 'value' => $pendingTasksCount, 'detail' => 'tarefas ainda não concluídas', 'tone' => 'bg-[#F0F4F6] text-[#667680]'],
                ['label' => 'Tarefas concluídas', 'value' => $completedTasksCount, 'detail' => 'tarefas finalizadas', 'tone' => 'bg-[#E4F3F0] text-[#2E8B74]'],
                ['label' => 'Tarefas atrasadas', 'value' => $overdueTasksCount, 'detail' => 'prazos vencidos em aberto', 'tone' => 'bg-[#FBE8E8] text-[#C44B4B]'],
                ['label' => 'Documentos gerados', 'value' => $documentsCount, 'detail' => 'versões registradas no histórico', 'tone' => 'bg-[#F2EAFB] text-[#7752A5]'],
            ];
        @endphp

        <section>
            <div class="mb-3">
                <h2 class="text-lg font-bold text-[#24313A]">Indicadores gerais</h2>
                <p class="mt-1 text-sm text-[#667680]">Dados reais consolidados conforme sua permissão de acesso</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cards as $card)
                    <article class="rounded-2xl border border-[#DCE3E7] bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-[#667680]">{{ $card['label'] }}</p>
                                <p class="mt-2 text-3xl font-bold text-[#24313A]">{{ $card['value'] }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['tone'] }}">
                                <span class="h-2.5 w-2.5 rounded-full bg-current"></span>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-[#667680]">{{ $card['detail'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @foreach ([['title' => 'Situação dos projetos', 'items' => $projectStatuses], ['title' => 'Tarefas por etapa', 'items' => $taskStatuses]] as $chart)
                @php($chartTotal = max(1, $chart['items']->sum('value')))
                <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-[#24313A]">{{ $chart['title'] }}</h2>
                    <div class="mt-5 space-y-4">
                        @foreach ($chart['items'] as $item)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium text-[#52616A]">{{ $item['label'] }}</span>
                                    <span class="font-bold text-[#24313A]">{{ $item['value'] }}</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-[#EEF2F4]">
                                    <div class="h-full rounded-full" style="width: {{ ($item['value'] / $chartTotal) * 100 }}%; background-color: {{ $item['color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between border-b border-[#DCE3E7] px-5 py-4">
                    <div>
                        <h2 class="font-bold text-[#24313A]">Progresso dos projetos</h2>
                        <p class="mt-1 text-sm text-[#667680]">Tarefas concluídas ÷ total de tarefas ativas</p>
                    </div>
                    <a href="{{ route('projects.index') }}" class="text-sm font-semibold text-[#287EA1] hover:underline">Ver projetos</a>
                </div>
                <div class="divide-y divide-[#E8EDF0]">
                    @forelse ($progressProjects as $project)
                        <a href="{{ route('projects.show', $project) }}" class="block px-5 py-4 transition hover:bg-[#F8FAFB]">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-[#287EA1]">{{ $project->code }}</p>
                                    <p class="truncate text-sm font-bold text-[#24313A]">{{ $project->name }}</p>
                                </div>
                                <span class="text-sm font-bold text-[#24313A]">{{ $project->progress_percentage }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-[#EEF2F4]">
                                <div class="h-full rounded-full bg-[#1D5D73]" style="width: {{ $project->progress_percentage }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-[#667680]">{{ $project->completed_tasks_count }} de {{ $project->active_tasks_count }} tarefas concluídas</p>
                        </a>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-[#667680]">Nenhum projeto disponível.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Exigem atenção</h2>
                <p class="mt-1 text-sm text-[#667680]">Projetos ou tarefas com prazo vencido</p>
                <div class="mt-4 space-y-3">
                    @forelse ($attentionProjects as $project)
                        <a href="{{ route('projects.show', $project) }}" class="block rounded-xl border border-[#F1D2D2] bg-[#FFF8F8] p-3 transition hover:border-[#C44B4B]">
                            <p class="text-xs font-semibold text-[#C44B4B]">{{ $project->code }}</p>
                            <p class="mt-1 text-sm font-bold text-[#24313A]">{{ $project->name }}</p>
                            <p class="mt-1 text-xs text-[#667680]">{{ $project->overdue_tasks_count }} tarefa(s) atrasada(s)</p>
                        </a>
                    @empty
                        <div class="rounded-xl bg-[#F3FAF8] p-4 text-sm text-[#26735F]">Nenhuma pendência crítica encontrada.</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
                <div class="border-b border-[#DCE3E7] px-5 py-4">
                    <h2 class="font-bold text-[#24313A]">Próximos prazos</h2>
                    <p class="mt-1 text-sm text-[#667680]">Tarefas abertas ordenadas pela data de entrega</p>
                </div>
                <div class="divide-y divide-[#E8EDF0]">
                    @forelse ($upcomingDeadlines as $task)
                        <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="flex items-center justify-between gap-4 px-5 py-3.5 hover:bg-[#F8FAFB]">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[#24313A]">{{ $task->code }} · {{ $task->title }}</p>
                                <p class="mt-1 truncate text-xs text-[#667680]">{{ $task->project->name }} · {{ $task->responsible?->name ?? 'Sem responsável' }}</p>
                            </div>
                            <span class="flex-none text-xs font-bold text-[#9A6415]">{{ $task->due_date->format('d/m/Y') }}</span>
                        </a>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-[#667680]">Nenhum prazo futuro cadastrado.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
                <div class="border-b border-[#DCE3E7] px-5 py-4">
                    <h2 class="font-bold text-[#24313A]">Atividades recentes</h2>
                    <p class="mt-1 text-sm text-[#667680]">Últimos eventos registrados no histórico consolidado</p>
                </div>
                <div class="divide-y divide-[#E8EDF0]">
                    @forelse ($recentActivities as $activity)
                        <a href="{{ route('projects.history.index', $activity->project) }}" class="flex gap-3 px-5 py-3.5 hover:bg-[#F8FAFB]">
                            <span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-[#2E8B74]"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#24313A]">{{ $activity->description }}</p>
                                <p class="mt-1 truncate text-xs text-[#667680]">{{ $activity->project->code }} · {{ $activity->user?->name ?? 'Sistema' }} · {{ $activity->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-[#667680]">Nenhuma atividade registrada.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
