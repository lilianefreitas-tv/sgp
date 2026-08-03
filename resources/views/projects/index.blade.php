<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Projetos</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre e acompanhe os projetos de software</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <form method="GET" action="{{ route('projects.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="sm:col-span-2">
                        <label for="search" class="sgp-field-label">Pesquisar</label>
                        <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Código ou nome do projeto">
                    </div>
                    <div>
                        <label for="status" class="sgp-field-label">Status</label>
                        <select id="status" name="status" class="sgp-input">
                            <option value="">Todos</option>
                            @foreach (\App\Enums\ProjectStatus::options() as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="level" class="sgp-field-label">Nível</label>
                        <select id="level" name="level" class="sgp-input">
                            <option value="">Todos</option>
                            @foreach (\App\Enums\ManagementLevel::options() as $value => $label)
                                <option value="{{ $value }}" @selected($level === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="archive" class="sgp-field-label">Arquivo</label>
                        <div class="flex gap-2">
                            <select id="archive" name="archive" class="sgp-input">
                                <option value="current" @selected($archive === 'current')>Atuais</option>
                                <option value="archived" @selected($archive === 'archived')>Arquivados</option>
                            </select>
                            <button class="rounded-lg bg-[#E6F0F3] px-4 text-sm font-semibold text-[#123B4A] hover:bg-[#D8E8ED]">Filtrar</button>
                        </div>
                    </div>
                </form>

                @if (Auth::user()->canCreateProjects())
                    <a href="{{ route('projects.create') }}" class="sgp-button-primary whitespace-nowrap xl:w-auto">Novo projeto</a>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Projeto</th>
                            <th class="px-5 py-4">Cliente ou unidade</th>
                            <th class="px-5 py-4">Responsável</th>
                            <th class="px-5 py-4">Nível</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Equipe</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($projects as $project)
                            <tr class="text-sm text-[#24313A] hover:bg-[#FBFCFD]">
                                <td class="px-5 py-4">
                                    <p class="text-xs font-semibold text-[#287EA1]">{{ $project->code }}</p>
                                    <p class="mt-1 font-semibold">{{ $project->name }}</p>
                                    @if (!$project->is_active)<p class="mt-1 text-xs text-[#C44B4B]">Registro inativo</p>@endif
                                </td>
                                <td class="px-5 py-4">{{ $project->client?->name ?? 'Sem demandante vinculado' }}</td>
                                <td class="px-5 py-4">{{ $project->manager->name }}</td>
                                <td class="px-5 py-4">{{ $project->management_level->label() }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span></td>
                                <td class="px-5 py-4">{{ $project->members_count }}</td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('projects.show', $project) }}" class="sgp-link">Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhum projeto encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($projects->hasPages())
                <div class="border-t border-[#DCE3E7] px-5 py-4">{{ $projects->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
