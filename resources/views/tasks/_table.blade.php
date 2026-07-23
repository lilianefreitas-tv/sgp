<section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
    <div class="border-b border-[#DCE3E7] px-6 py-5"><h2 class="font-bold text-[#24313A]">Tarefas cadastradas</h2><p class="mt-1 text-sm text-[#667680]">{{ $tasks->total() }} {{ $tasks->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}</p></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#E8EDF0]">
            <thead class="bg-[#F8FAFB]"><tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]"><th class="px-6 py-4">Tarefa</th>@if($showProject)<th class="px-6 py-4">Projeto</th>@endif<th class="px-6 py-4">Prioridade</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Responsável</th><th class="px-6 py-4">Prazo</th><th class="px-6 py-4 text-right">Ações</th></tr></thead>
            <tbody class="divide-y divide-[#E8EDF0]">
                @forelse($tasks as $task)
                    <tr class="text-sm text-[#24313A] {{ $task->is_active ? '' : 'bg-[#FAFBFB] opacity-75' }}">
                        <td class="max-w-md px-6 py-4"><a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}" class="font-semibold text-[#1D5D73] hover:underline">{{ $task->code }} · {{ $task->title }}</a><p class="mt-1 text-xs text-[#667680]">@if($task->requirement){{ $task->requirement->code }} · {{ $task->requirement->title }}@else Sem requisito vinculado @endif</p></td>
                        @if($showProject)<td class="px-6 py-4"><p class="text-xs font-semibold text-[#287EA1]">{{ $task->project->code }}</p><p class="mt-1 font-medium">{{ $task->project->name }}</p></td>@endif
                        <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $task->priority->badgeClasses() }}">{{ $task->priority->label() }}</span></td>
                        <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span></td>
                        <td class="px-6 py-4">{{ $task->responsible?->name ?? 'Não definido' }}</td>
                        <td class="px-6 py-4 {{ $task->due_date && $task->due_date->isPast() && $task->status->value !== 'completed' ? 'font-semibold text-[#C44B4B]' : '' }}">{{ $task->due_date?->format('d/m/Y') ?? 'Não definido' }}</td>
                        <td class="px-6 py-4 text-right"><a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}" class="font-semibold text-[#287EA1] hover:underline">Visualizar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showProject ? 7 : 6 }}" class="px-6 py-14 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E4F3F0] text-[#2E8B74]"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m5 12 4 4L19 6M4 21h16"/></svg></div><p class="mt-4 font-semibold text-[#24313A]">Nenhuma tarefa encontrada</p><p class="mt-1 text-sm text-[#667680]">Cadastre a primeira tarefa ou ajuste os filtros da pesquisa.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tasks->hasPages())<div class="border-t border-[#E8EDF0] px-6 py-4">{{ $tasks->links() }}</div>@endif
</section>
