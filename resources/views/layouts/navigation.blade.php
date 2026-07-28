<div>
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-slate-950/45 lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
        style="display: none;"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full
               flex-col bg-[#123B4A] text-white shadow-xl
               transition-transform duration-300 lg:translate-x-0"
        :class="{ 'translate-x-0': sidebarOpen }"
    >
        <div
            class="flex h-20 items-center justify-between
                   border-b border-white/10 px-6"
        >
            <a
                href="{{ route('dashboard') }}"
                class="flex items-center gap-3"
            >
                <x-application-logo
                    class="h-11 w-11 flex-none text-[#123B4A]"
                />

                <div>
                    <p class="text-xl font-bold tracking-tight">
                        SGP
                    </p>

                    <p class="text-xs text-slate-300">
                        Gestão de Projetos
                    </p>
                </div>
            </a>

            <button
                type="button"
                class="rounded-lg p-2 text-slate-300
                       hover:bg-white/10 lg:hidden"
                @click="sidebarOpen = false"
                aria-label="Fechar menu"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18 18 6M6 6l12 12"
                    />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-6">
            <p
                class="mb-3 px-3 text-xs font-semibold uppercase
                       tracking-widest text-slate-400"
            >
                Visão geral
            </p>

            <a
                href="{{ route('dashboard') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('dashboard')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z"
                    />
                </svg>

                Painel
            </a>

            <a
                href="{{ route('calendar.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('calendar.*', 'projects.calendar.*', 'projects.schedule.*')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
                </svg>

                Calendário
            </a>

            <p
                class="mb-3 mt-8 px-3 text-xs font-semibold uppercase
                       tracking-widest text-slate-400"
            >
                Gerenciamento
            </p>

            <a
                href="{{ route('projects.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs(
                                'projects.index',
                                'projects.create',
                                'projects.show',
                                'projects.edit',
                            )
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M4 7h16M4 7l2-3h5l2 3M5 7v13h14V7M9 11h6"
                    />
                </svg>

                Projetos
            </a>

            @if (Auth::user()->canCreateProjects())
                <a
                    href="{{ route('clients.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-3
                           text-sm font-medium transition
                           {{ request()->routeIs('clients.*')
                                ? 'bg-white/15 text-white'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M3 21h18M5 21V7l7-4 7 4v14M9 10h6M9 14h6M9 18h6"
                        />
                    </svg>

                    Clientes e unidades
                </a>
            @endif

            <a
                href="{{ route('requirements.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('requirements.*', 'projects.requirements.*')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M9 12h6M9 16h6M8 3h8l4 4v14H4V3h4Zm8 0v5h5"
                    />
                </svg>

                Requisitos
            </a>

            <a
                href="{{ route('tasks.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('tasks.*', 'projects.tasks.*')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="m5 12 4 4L19 6M4 21h16"
                    />
                </svg>

                Tarefas
            </a>

            <a
                href="{{ route('kanban.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('kanban.*', 'projects.kanban.*')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M4 5h4v14H4V5Zm6 0h4v9h-4V5Zm6 0h4v11h-4V5Z"
                    />
                </svg>

                Kanban
            </a>

            <a
                href="{{ route('documents.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium transition
                       {{ request()->routeIs('documents.*', 'projects.documents.*')
                            ? 'bg-white/15 text-white'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M7 3h7l4 4v14H7V3Zm7 0v5h5M10 12h5M10 16h5"
                    />
                </svg>

                Documentos
            </a>

            <div
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium text-slate-400"
                title="Funcionalidade em desenvolvimento"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="m5 12 4 4L19 6M4 21h16"
                    />
                </svg>

                Testes
            </div>

            <div
                class="flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium text-slate-400"
                title="Funcionalidade em desenvolvimento"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M12 3 3 7.5 12 12l9-4.5L12 3Zm-9 9 9 4.5 9-4.5M3 16.5 12 21l9-4.5"
                    />
                </svg>

                Rastreabilidade
            </div>

            @if (Auth::user()->isAdministrator())
                <p class="mb-3 mt-8 px-3 text-xs font-semibold uppercase tracking-widest text-slate-400">
                    Administração
                </p>

                <a
                    href="{{ route('users.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                           {{ request()->routeIs('users.*') ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>

                    Usuários
                </a>

                <a
                    href="{{ route('document-templates.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                           {{ request()->routeIs('document-templates.*') ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 4h9l3 3v13H6V4Zm9 0v4h4M9 12h6M9 16h6" />
                    </svg>

                    Modelos de documentos
                </a>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <a
                href="{{ route('profile.edit') }}"
                class="mb-2 flex items-center gap-3 rounded-lg px-3 py-3
                       text-sm font-medium text-slate-300 transition
                       hover:bg-white/10 hover:text-white"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 9a7 7 0 0 0-14 0"
                    />
                </svg>

                Meu perfil
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-lg
                           px-3 py-3 text-sm font-medium text-slate-300
                           transition hover:bg-white/10 hover:text-white"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M10 17 15 12 10 7M15 12H3M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"
                        />
                    </svg>

                    Sair
                </button>
            </form>
        </div>
    </aside>
</div>
