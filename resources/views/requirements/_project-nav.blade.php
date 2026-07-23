<nav class="flex flex-wrap gap-2 rounded-xl border border-[#DCE3E7] bg-white p-2 shadow-sm" aria-label="Áreas do projeto">
    <a href="{{ route('projects.show', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.show') ? 'bg-[#123B4A] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Visão geral
    </a>
    <a href="{{ route('projects.show', $project) }}#equipe"
       class="rounded-lg px-4 py-2 text-sm font-semibold text-[#667680] transition hover:bg-[#F3F6F7] hover:text-[#24313A]">
        Equipe
    </a>
    <a href="{{ route('projects.requirements.index', $project) }}"
       class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('projects.requirements.*') ? 'bg-[#123B4A] text-white' : 'text-[#667680] hover:bg-[#F3F6F7] hover:text-[#24313A]' }}">
        Requisitos
    </a>
</nav>
