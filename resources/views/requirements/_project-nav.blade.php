<nav class="flex flex-wrap gap-2 rounded-xl border border-[#DCE3E7] bg-white p-2 shadow-sm" aria-label="Áreas do projeto">
    <a href="{{ route('projects.show', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.show') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Visão geral
    </a>
    <a href="{{ route('projects.show', $project) }}#equipe"
       class="rounded-lg px-4 py-2 text-sm font-semibold text-[#667680] transition hover:bg-[#F3F6F7] hover:text-[#24313A]">
        Equipe
    </a>
    <a href="{{ route('projects.requirements.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.requirements.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Requisitos
    </a>
    <a href="{{ route('projects.tasks.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.tasks.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Tarefas
    </a>
    <a href="{{ route('projects.kanban.show', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.kanban.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Kanban
    </a>
    <a href="{{ route('projects.schedule.show', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.schedule.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Cronograma
    </a>
    <a href="{{ route('projects.calendar.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.calendar.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Calendário
    </a>
    <a href="{{ route('projects.documents.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.documents.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Documentos
    </a>
    <a href="{{ route('projects.comments.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.comments.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Comentários
    </a>
    <a href="{{ route('projects.attachments.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.attachments.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Anexos
    </a>
    <a href="{{ route('projects.change-requests.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.change-requests.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Mudanças
    </a>
    <a href="{{ route('projects.tests.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.tests.*', 'projects.homologations.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Testes
    </a>
    <a href="{{ route('projects.traceability.show', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.traceability.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Rastreabilidade
    </a>
    <a href="{{ route('projects.history.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.history.*') ? 'bg-[#185063] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Histórico
    </a>
</nav>
