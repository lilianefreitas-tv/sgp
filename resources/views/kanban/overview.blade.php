<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Kanban</h1>
            <p class="mt-1 text-sm text-[#667680]">Escolha um projeto para acompanhar visualmente o fluxo das tarefas.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $project)
                @php
                    $progress = $project->active_tasks_count > 0
                        ? (int) round(($project->completed_tasks_count / $project->active_tasks_count) * 100)
                        : 0;
                @endphp

                <a
                    href="{{ route('projects.kanban.show', $project) }}"
                    class="group rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#9FC9D7] hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#287EA1]">{{ $project->code }}</p>
                            <h2 class="mt-1 truncate text-base font-bold text-[#24313A] group-hover:text-[#123B4A]">{{ $project->name }}</h2>
                            <p class="mt-1 truncate text-sm text-[#667680]">{{ $project->client?->name ?? 'Sem cliente ou unidade' }}</p>
                        </div>

                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-[#E4F3F0] text-[#2E8B74]">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h4v14H4V5Zm6 0h4v9h-4V5Zm6 0h4v11h-4V5Z" />
                            </svg>
                        </span>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 flex items-center justify-between text-xs font-medium text-[#667680]">
                            <span>{{ $project->completed_tasks_count }} de {{ $project->active_tasks_count }} tarefas concluídas</span>
                            <span>{{ $progress }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-[#E9EEF1]">
                            <div class="h-full rounded-full bg-[#2E8B74]" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-[#CBD5DA] bg-white px-6 py-14 text-center">
                    <p class="font-semibold text-[#24313A]">Nenhum projeto disponível</p>
                    <p class="mt-2 text-sm text-[#667680]">Os quadros aparecerão aqui quando você participar de um projeto ativo.</p>
                </div>
            @endforelse
        </div>

        {{ $projects->links() }}
    </div>
</x-app-layout>
