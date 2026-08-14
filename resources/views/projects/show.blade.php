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

        @if ($project->initiative)
            <a href="{{ route('initiatives.artifacts.index', $project->initiative) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-[#BFE2D9] bg-[#F3FAF8] p-5 transition hover:border-[#2E8B74] hover:bg-[#EDF8F5]">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-[#2E8B74]">Documentação de origem</p>
                    <p class="mt-1 font-bold text-[#256C5C]">Dossiê da Iniciativa {{ $project->initiative->code }}</p>
                    <p class="mt-1 text-sm text-[#667680]">Consulte a necessidade, a descoberta e a trajetória comercial que deram origem a este projeto.</p>
                </div>
                <span class="text-xl text-[#2E8B74]">→</span>
            </a>
        @endif

        <a href="{{ route('projects.origin-documents.index', $project) }}" class="group flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-[#CFC5E2] bg-gradient-to-r from-[#FBFAFD] to-[#F3EFF9] p-5 shadow-[0_6px_18px_rgba(89,65,115,0.12)] ring-1 ring-[#E8E1F1] transition duration-200 hover:-translate-y-0.5 hover:border-[#8064A2] hover:shadow-[0_10px_24px_rgba(89,65,115,0.18)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8064A2]">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-[#8064A2]">Documentação de origem</p>
                <p class="mt-1 font-bold text-[#594173]">Arquivos recebidos antes do acompanhamento no SGP</p>
                <p class="mt-1 text-sm text-[#667680]">Contratos, TAP, visão, propostas e outras referências preexistentes, preservadas com versões e integridade.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full border border-[#DED5EA] bg-white px-3 py-1 text-sm font-bold text-[#8064A2] shadow-sm">{{ $project->origin_document_versions_count }} versões</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#8064A2] text-lg text-white transition group-hover:translate-x-1">→</span>
            </div>
        </a>

        <a href="{{ route('projects.baselines.index', $project) }}" class="group flex items-center justify-between gap-4 rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-5 shadow-sm transition hover:border-[#287EA1] hover:bg-[#EDF6F8]">
            <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#287EA1]">Governança da configuração</p><p class="mt-1 font-bold text-[#123B4A]">Baselines do projeto</p><p class="mt-1 text-sm text-[#667680]">Constitua versões imutáveis do escopo, requisitos, documentos e contratos vigentes.</p></div>
            <div class="flex items-center gap-3"><span class="rounded-full bg-white px-3 py-1 text-sm font-bold text-[#1D5D73]">{{ $project->baselines_count }} versões</span><span class="text-xl text-[#287EA1]">→</span></div>
        </a>

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
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Cliente ou unidade</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->client?->name ?? 'Sem demandante vinculado' }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->client?->type?->label() ?? 'Projeto interno ou vínculo não aplicável' }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Responsável principal</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->manager->name }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->manager->email }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Nível de gestão</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->management_level->label() }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->methodologyLabel() }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Prazo</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->expected_end_date?->format('d/m/Y') ?? 'Não definido' }}</p><p class="mt-1 text-xs text-[#667680]">Início: {{ $project->start_date?->format('d/m/Y') ?? 'não informado' }}</p></article>
        </section>

        <section class="rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-[#123B4A]">Configuração adaptativa</h2>
                    <p class="mt-1 text-sm text-[#667680]">As dimensões são independentes e orientam a gestão sem ocultar dados ou presumir módulos futuros.</p>
                </div>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-white bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Natureza</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->execution_nature->label() }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->execution_nature->description() }}</p></article>
                <article class="rounded-xl border border-white bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Tratamento financeiro</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->financial_management_mode->label() }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->financial_management_mode->description() }}</p></article>
                <article class="rounded-xl border border-white bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Nível de gestão</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->management_level->label() }}</p><p class="mt-1 text-xs text-[#667680]">{{ $project->management_level->description() }}</p></article>
                <article class="rounded-xl border border-white bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Metodologia</p><p class="mt-2 font-semibold text-[#24313A]">{{ $project->methodologyLabel() }}</p><p class="mt-1 text-xs text-[#667680]">Organização do fluxo de trabalho do projeto.</p></article>
            </div>
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

        <a href="{{ route('projects.documents.index', $project) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-[#C9DCE4] bg-[#F5F9FB] p-5 transition hover:border-[#287EA1] hover:bg-[#EDF6F8]">
            <div>
                <p class="font-bold text-[#1D5D73]">Documentos do projeto</p>
                <p class="mt-1 text-sm text-[#667680]">Gere artefatos em DOCX e PDF e consulte o histórico de versões.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-white px-3 py-1 text-sm font-bold text-[#287EA1]">{{ $project->documents_count }} gerados</span>
                <span class="text-xl text-[#287EA1]">→</span>
            </div>
        </a>

        <a href="{{ route('projects.artifacts.index', $project) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-5 transition hover:border-[#287EA1] hover:bg-[#EDF6F8]">
            <div>
                <p class="font-bold text-[#123B4A]">Registros documentais complementares</p>
                <p class="mt-1 text-sm text-[#667680]">Gerencie conteúdo versionado, papéis, revisões e aprovações documentais.</p>
            </div>
            <span class="text-xl text-[#287EA1]">→</span>
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
