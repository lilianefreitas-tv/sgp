<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                @if ($project->archived_at)<span class="rounded-full bg-[#F3F5F6] px-3 py-1 text-xs font-semibold text-[#667680]">Arquivado</span>@endif
            </div>
            <h1 class="mt-1 truncate text-xl font-bold text-[#24313A]">{{ $project->name }}</h1>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @include('requirements._project-nav')

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('projects.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] bg-white px-4 py-2.5 text-sm font-semibold text-[#24313A] hover:bg-[#F5F7F9]">Voltar</a>
            @if ($canManage)
                <a href="{{ route('projects.edit', $project) }}" class="sgp-button-primary w-auto px-4 py-2.5">Editar projeto</a>
                @if ($project->archived_at)
                    <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf @method('PATCH')<button class="inline-flex rounded-lg border border-[#2E8B74] px-4 py-2.5 text-sm font-semibold text-[#2E8B74] hover:bg-[#EDF8F5]">Restaurar</button></form>
                @else
                    <form method="POST" action="{{ route('projects.archive', $project) }}" onsubmit="return confirm('Arquivar este projeto? Ele continuará disponível para consulta histórica.')">@csrf @method('PATCH')<button class="inline-flex rounded-lg border border-[#DCE3E7] px-4 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Arquivar</button></form>
                @endif
            @endif
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Cliente ou unidade</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->client->name }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->client->type->label() }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Responsável principal</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->manager->name }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->manager->email }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Nível de gestão</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->management_level->label() }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->methodology ?: 'Metodologia não informada' }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Prazo</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->expected_end_date?->format('d/m/Y') ?? 'Não definido' }}</p><p class="mt-1 text-xs text-[#667680]">Início: {{ $project->start_date?->format('d/m/Y') ?? 'não informado' }}</p></article>
        </section>

        <a href="{{ route('projects.requirements.index', $project) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-5 transition hover:border-[#287EA1] hover:bg-[#EDF6F8]">
            <div>
                <p class="font-bold text-[#123B4A]">Requisitos do projeto</p>
                <p class="mt-1 text-sm text-[#667680]">Cadastre, priorize e acompanhe as necessidades desta solução.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-white px-3 py-1 text-sm font-bold text-[#1D5D73]">{{ $project->active_requirements_count }} ativos</span>
                <span class="text-xl text-[#287EA1]">→</span>
            </div>
        </a>

        <a href="{{ route('projects.tasks.index', $project) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-[#BFE2D9] bg-[#F3FAF8] p-5 transition hover:border-[#2E8B74] hover:bg-[#EDF8F5]">
            <div>
                <p class="font-bold text-[#256C5C]">Tarefas do projeto</p>
                <p class="mt-1 text-sm text-[#667680]">Planeje, atribua responsáveis e acompanhe a execução das atividades.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-white px-3 py-1 text-sm font-bold text-[#2E8B74]">{{ $project->active_tasks_count }} ativas</span>
                <span class="text-xl text-[#2E8B74]">→</span>
            </div>
        </a>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-[#24313A]">Visão geral</h2>
            <div class="mt-5 grid gap-5 lg:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Objetivo</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $project->objective }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Descrição</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $project->description ?: 'Não informada.' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Justificativa</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $project->justification ?: 'Não informada.' }}</p></div>
            </div>
        </section>

        <section id="equipe" class="scroll-mt-24 rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="text-base font-bold text-[#24313A]">Equipe e participantes</h2>
                <p class="mt-1 text-sm text-[#667680]">Os papéis valem somente neste projeto e não alteram o perfil global do usuário.</p>
            </div>

            @if ($canManage)
                <form method="POST" action="{{ route('projects.members.store', $project) }}" class="border-b border-[#E8EDF0] bg-[#F8FAFB] p-6">
                    @csrf
                    <div class="grid gap-5 lg:grid-cols-[minmax(220px,1fr)_minmax(0,2fr)_auto] lg:items-end">
                        <div>
                            <label for="user_id" class="sgp-field-label">Usuário</label>
                            <select id="user_id" name="user_id" class="sgp-input" required>
                                <option value="">Selecione</option>
                                @foreach ($users as $userOption)<option value="{{ $userOption->id }}" @selected((string) old('user_id') === (string) $userOption->id)>{{ $userOption->name }}</option>@endforeach
                            </select>
                        </div>
                        <fieldset>
                            <legend class="sgp-field-label">Papéis no projeto</legend>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($roles as $value => $label)
                                    <label class="flex items-center gap-2 rounded-lg border border-[#DCE3E7] bg-white px-3 py-2 text-sm text-[#24313A]">
                                        <input type="checkbox" name="roles[]" value="{{ $value }}" class="rounded border-[#B8C5CB] text-[#123B4A] focus:ring-[#287EA1]" @checked(in_array($value, old('roles', []), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <button class="sgp-button-primary lg:w-auto">Adicionar ou ajustar</button>
                    </div>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F8FAFB]"><tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]"><th class="px-6 py-4">Participante</th><th class="px-6 py-4">Papéis neste projeto</th>@if($canManage)<th class="px-6 py-4 text-right">Ações</th>@endif</tr></thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($members as $member)
                            <tr class="text-sm text-[#24313A]">
                                <td class="px-6 py-4"><p class="font-semibold">{{ $member['user']->name }}</p><p class="mt-1 text-xs text-[#667680]">{{ $member['user']->email }}</p></td>
                                <td class="px-6 py-4"><div class="flex flex-wrap gap-2">@foreach($member['roles'] as $role)<span class="rounded-full bg-[#E6F0F3] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $role->label() }}</span>@endforeach</div></td>
                                @if ($canManage)
                                    <td class="px-6 py-4 text-right">
                                        @if ($member['user']->id === $project->manager_id)
                                            <span class="text-xs font-semibold text-[#667680]">Responsável principal</span>
                                        @else
                                            <form method="POST" action="{{ route('projects.members.destroy', [$project, $member['user']]) }}" onsubmit="return confirm('Remover este participante da equipe? O histórico será preservado.')">@csrf @method('DELETE')<button class="text-sm font-semibold text-[#C44B4B] hover:underline">Remover</button></form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 3 : 2 }}" class="px-6 py-10 text-center text-sm text-[#667680]">Nenhum participante vinculado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
