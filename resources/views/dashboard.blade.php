<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">
                Painel
            </h1>

            <p class="mt-1 text-sm text-[#667680]">
                Visão geral do ambiente de gestão
            </p>
        </div>
    </x-slot>

    <div class="space-y-5">
        {{-- Boas-vindas --}}
        <section
            class="relative overflow-hidden rounded-2xl px-6 py-5
                   text-white shadow-sm sm:px-8"
            style="background: linear-gradient(135deg, #123B4A 0%, #1D5D73 100%);"
        >
            <div class="relative z-10 max-w-2xl">
                <p
                    class="text-sm font-semibold uppercase tracking-widest
                           text-[#A8E2D7]"
                >
                    Bem-vinda ao SGP
                </p>

                <h2 class="mt-2 text-2xl font-bold text-white">
                    Olá, {{ Auth::user()->name }}!
                </h2>

                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#E4EEF1]">
                    Acompanhe projetos, requisitos, testes e decisões em um
                    ambiente integrado e rastreável.
                </p>

                <div class="mt-4">
                    <a
                        href="{{ Auth::user()->canCreateProjects() ? route('projects.create') : route('projects.index') }}"
                        class="inline-flex items-center rounded-lg border
                               border-white/20 bg-white/10 px-4 py-2
                               text-sm font-semibold text-white transition hover:bg-white/15"
                    >
                        {{ Auth::user()->canCreateProjects() ? 'Cadastrar novo projeto' : 'Ver meus projetos' }}
                    </a>
                </div>
            </div>

            <div
                class="absolute -right-16 -top-20 h-64 w-64
                       rounded-full border border-white/10"
                aria-hidden="true"
            ></div>

            <div
                class="absolute -bottom-24 right-24 h-52 w-52
                       rounded-full border border-white/10"
                aria-hidden="true"
            ></div>
        </section>

        {{-- Indicadores --}}
        <section>
            <div class="mb-3">
                <h2 class="text-lg font-bold text-[#24313A]">
                    Indicadores gerais
                </h2>

                <p class="mt-1 text-sm text-[#667680]">
                    Resumo atual dos registros do sistema
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Projetos --}}
                <article
                    class="rounded-2xl border border-[#DCE3E7]
                           bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-[#667680]">
                                Projetos ativos
                            </p>

                            <p class="mt-2 text-3xl font-bold text-[#24313A]">
                                {{ $activeProjectsCount }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 flex-none items-center
                                   justify-center rounded-xl bg-[#E6F0F3]
                                   text-[#123B4A]"
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
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-[#667680]">
                        {{ $activeProjectsCount === 1 ? '1 projeto disponível' : $activeProjectsCount.' projetos disponíveis' }}
                    </p>
                </article>

                {{-- Requisitos --}}
                <article
                    class="rounded-2xl border border-[#DCE3E7]
                           bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-[#667680]">
                                Requisitos
                            </p>

                            <p class="mt-2 text-3xl font-bold text-[#24313A]">
                                0
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 flex-none items-center
                                   justify-center rounded-xl bg-[#E8F1FA]
                                   text-[#287EA1]"
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
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-[#667680]">
                        Aguardando projetos
                    </p>
                </article>

                {{-- Testes --}}
                <article
                    class="rounded-2xl border border-[#DCE3E7]
                           bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-[#667680]">
                                Testes executados
                            </p>

                            <p class="mt-2 text-3xl font-bold text-[#24313A]">
                                0
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 flex-none items-center
                                   justify-center rounded-xl bg-[#E4F3F0]
                                   text-[#2E8B74]"
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
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-[#667680]">
                        Nenhum teste registrado
                    </p>
                </article>

                {{-- Pendências --}}
                <article
                    class="rounded-2xl border border-[#DCE3E7]
                           bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-[#667680]">
                                Pendências
                            </p>

                            <p class="mt-2 text-3xl font-bold text-[#24313A]">
                                0
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 flex-none items-center
                                   justify-center rounded-xl bg-[#FFF3DE]
                                   text-[#D89427]"
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
                                    d="M12 8v5m0 4h.01M10.3 4.4 2.8 18a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 4.4a2 2 0 0 0-3.4 0Z"
                                />
                            </svg>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-[#667680]">
                        Tudo em ordem
                    </p>
                </article>
            </div>
        </section>

        {{-- Conteúdo inferior --}}
        <section class="grid gap-4 lg:grid-cols-3">
            {{-- Projetos recentes --}}
            <article
                class="rounded-2xl border border-[#DCE3E7]
                       bg-white shadow-sm lg:col-span-2"
            >
                <div
                    class="flex items-center justify-between
                           border-b border-[#DCE3E7] px-5 py-4"
                >
                    <div>
                        <h2 class="font-bold text-[#24313A]">
                            Projetos recentes
                        </h2>

                        <p class="mt-1 text-sm text-[#667680]">
                            Últimos projetos cadastrados
                        </p>
                    </div>

                    <span
                        class="rounded-full bg-[#F0F4F6] px-3 py-1
                               text-xs font-semibold text-[#667680]"
                    >
                        {{ $recentProjects->count() }} {{ $recentProjects->count() === 1 ? 'registro' : 'registros' }}
                    </span>
                </div>

                @forelse ($recentProjects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="flex items-center justify-between gap-4 border-b border-[#E8EDF0] px-5 py-4 last:border-b-0 hover:bg-[#F8FAFB]">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-[#287EA1]">{{ $project->code }}</p>
                            <p class="mt-1 truncate text-sm font-semibold text-[#24313A]">{{ $project->name }}</p>
                            <p class="mt-1 truncate text-xs text-[#667680]">{{ $project->client->name }} • {{ $project->manager->name }}</p>
                        </div>
                        <span class="flex-none rounded-full px-3 py-1 text-xs font-semibold {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
                    </a>
                @empty
                    <div class="flex min-h-48 flex-col items-center justify-center px-6 py-7 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F0F3] text-[#123B4A]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 7l2-3h5l2 3M5 7v13h14V7M9 11h6" /></svg>
                        </div>
                        <h3 class="mt-4 font-bold text-[#24313A]">Nenhum projeto cadastrado</h3>
                        <p class="mt-2 max-w-md text-sm leading-6 text-[#667680]">Assim que o primeiro projeto for criado, suas informações principais aparecerão aqui.</p>
                    </div>
                @endforelse
            </article>

            {{-- Atalhos --}}
            <article
                class="rounded-2xl border border-[#DCE3E7]
                       bg-white p-5 shadow-sm"
            >
                <h2 class="font-bold text-[#24313A]">
                    Atalhos rápidos
                </h2>

                <p class="mt-1 text-sm text-[#667680]">
                    Acesse as principais áreas
                </p>

                <div class="mt-4 space-y-2">
                    <a
                        href="{{ Auth::user()->canCreateProjects() ? route('projects.create') : route('projects.index') }}"
                        class="flex items-center gap-3 rounded-xl border
                               border-[#DCE3E7] px-3 py-3 text-[#24313A]
                               transition hover:border-[#287EA1] hover:bg-[#F8FBFC]"
                    >
                        <div
                            class="flex h-9 w-9 flex-none items-center
                                   justify-center rounded-lg bg-[#E6F0F3] text-[#123B4A]"
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
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="text-sm font-semibold">
                                Novo projeto
                            </p>

                            <p class="mt-0.5 text-xs">
                                {{ Auth::user()->canCreateProjects() ? 'Cadastre a base do projeto' : 'Acesse seus projetos' }}
                            </p>
                        </div>
                    </a>

                    <div
                        class="flex items-center gap-3 rounded-xl border
                               border-[#DCE3E7] px-3 py-3 text-[#94A1A9]"
                        title="Funcionalidade em desenvolvimento"
                    >
                        <div
                            class="flex h-9 w-9 flex-none items-center
                                   justify-center rounded-lg bg-[#F0F4F6]"
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
                        </div>

                        <div>
                            <p class="text-sm font-semibold">
                                Novo requisito
                            </p>

                            <p class="mt-0.5 text-xs">
                                Depende de um projeto
                            </p>
                        </div>
                    </div>

                    <a
                        href="{{ route('profile.edit') }}"
                        class="flex items-center gap-3 rounded-xl border
                               border-[#DCE3E7] px-3 py-3 text-[#24313A]
                               transition hover:border-[#287EA1]
                               hover:bg-[#F8FBFC]"
                    >
                        <div
                            class="flex h-9 w-9 flex-none items-center
                                   justify-center rounded-lg bg-[#E4F3F0]
                                   text-[#2E8B74]"
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
                        </div>

                        <div>
                            <p class="text-sm font-semibold">
                                Meu perfil
                            </p>

                            <p class="mt-0.5 text-xs text-[#667680]">
                                Gerencie seus dados de acesso
                            </p>
                        </div>
                    </a>
                </div>
            </article>
        </section>

        {{-- Atividades recentes --}}
        <section
            class="rounded-2xl border border-[#DCE3E7]
                   bg-white p-5 shadow-sm"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-10 w-10 flex-none items-center
                           justify-center rounded-xl bg-[#E8F1FA]
                           text-[#287EA1]"
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
                            d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="font-bold text-[#24313A]">
                        Atividades recentes
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-[#667680]">
                        O histórico de alterações, movimentações e decisões
                        dos projetos será apresentado aqui.
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
