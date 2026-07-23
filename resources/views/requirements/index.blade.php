<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</p>
            <h1 class="truncate text-xl font-bold text-[#24313A]">Requisitos de {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre, priorize e acompanhe o que o projeto deverá entregar.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @include('requirements._project-nav')

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(210px,1.35fr)_repeat(4,minmax(110px,0.85fr))_auto_auto_auto] xl:items-end">
                <div class="sm:col-span-2 xl:col-span-1">
                    <label for="search" class="sgp-field-label">Pesquisar</label>
                    <input id="search" name="search" value="{{ $search }}" class="sgp-input" placeholder="Código, título ou descrição">
                </div>
                <div>
                    <label for="status" class="sgp-field-label">Status</label>
                    <select id="status" name="status" class="sgp-input">
                        <option value="">Todos</option>
                        @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="priority" class="sgp-field-label">Prioridade</label>
                    <select id="priority" name="priority" class="sgp-input">
                        <option value="">Todas</option>
                        @foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="sgp-field-label">Tipo</label>
                    <select id="type" name="type" class="sgp-input">
                        <option value="">Todos</option>
                        @foreach ($types as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="activity" class="sgp-field-label">Situação</label>
                    <select id="activity" name="activity" class="sgp-input">
                        <option value="active" @selected($activity === 'active')>Ativos</option>
                        <option value="inactive" @selected($activity === 'inactive')>Inativos</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg bg-[#E6F0F3] px-4 py-3 text-sm font-semibold text-[#123B4A] transition hover:bg-[#D8E8ED]">Filtrar</button>
                <a href="{{ route('projects.requirements.index', $project) }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-[#DCE3E7] px-4 py-3 text-sm font-semibold text-[#667680] transition hover:bg-[#F5F7F9]">Limpar filtros</a>
                @if ($canManage)
                    <a href="{{ route('projects.requirements.create', $project) }}" class="sgp-button-primary w-auto whitespace-nowrap px-4">Novo requisito</a>
                @endif
        </form>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#DCE3E7] px-6 py-5">
                <div>
                    <h2 class="font-bold text-[#24313A]">Requisitos cadastrados</h2>
                    <p class="mt-1 text-sm text-[#667680]">{{ $requirements->total() }} {{ $requirements->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-6 py-4">Requisito</th>
                            <th class="px-6 py-4">Tipo</th>
                            <th class="px-6 py-4">Prioridade</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Responsável</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($requirements as $requirement)
                            <tr class="text-sm text-[#24313A] {{ $requirement->is_active ? '' : 'bg-[#FAFBFB] opacity-75' }}">
                                <td class="max-w-md px-6 py-4">
                                    <a href="{{ route('projects.requirements.show', [$project, $requirement]) }}" class="font-semibold text-[#1D5D73] hover:underline">{{ $requirement->code }} · {{ $requirement->title }}</a>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-[#667680]">{{ $requirement->description ?: 'Sem descrição detalhada.' }}</p>
                                </td>
                                <td class="px-6 py-4">{{ $requirement->type->label() }}</td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $requirement->priority->badgeClasses() }}">{{ $requirement->priority->label() }}</span></td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $requirement->status->badgeClasses() }}">{{ $requirement->status->label() }}</span></td>
                                <td class="px-6 py-4">{{ $requirement->responsible?->name ?? 'Não definido' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('projects.requirements.show', [$project, $requirement]) }}" class="font-semibold text-[#287EA1] hover:underline">Visualizar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F0F3] text-[#123B4A]">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6M9 16h6M8 3h8l4 4v14H4V3h4Zm8 0v5h5"/></svg>
                                    </div>
                                    <p class="mt-4 font-semibold text-[#24313A]">Nenhum requisito encontrado</p>
                                    <p class="mt-1 text-sm text-[#667680]">Cadastre o primeiro requisito ou ajuste os filtros da pesquisa.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requirements->hasPages())
                <div class="border-t border-[#E8EDF0] px-6 py-4">{{ $requirements->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
