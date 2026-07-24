<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta
            name="description"
            content="SGP - Sistema de Gestão de Projetos de Software"
        >

        <title>{{ config('app.name', 'SGP') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link
            rel="icon"
            type="image/png"
            href="{{ asset('images/sgp-logo.png') }}"
        >

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap"
            rel="stylesheet"
        >

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="antialiased">
        <main class="min-h-screen bg-[#F5F7F9] lg:grid lg:grid-cols-2">
            <section
                class="relative flex min-h-[54vh] overflow-hidden px-7 py-8
                       text-white sm:px-12 sm:py-10 lg:min-h-screen
                       lg:flex-col lg:justify-between"
                style="background:
                    radial-gradient(circle at 88% 12%, rgba(94, 198, 178, .18), transparent 28%),
                    linear-gradient(145deg, #123B4A 0%, #174E60 55%, #1D5D73 100%);"
            >
                <div
                    class="pointer-events-none absolute -right-36 -top-32
                           h-[420px] w-[420px] rounded-full border
                           border-white/10"
                    aria-hidden="true"
                ></div>
                <div
                    class="pointer-events-none absolute -bottom-40 -left-36
                           h-[320px] w-[320px] rounded-full border
                           border-white/10"
                    aria-hidden="true"
                ></div>

                <div class="relative z-10 flex w-full flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <x-application-logo
                            class="h-14 w-14 flex-none text-[#123B4A]"
                        />

                        <div>
                            <p class="text-2xl font-bold tracking-tight">SGP</p>
                            <p class="text-sm text-slate-200">
                                Sistema de Gestão de Projetos de Software
                            </p>
                        </div>
                    </div>

                    <div class="my-14 max-w-xl lg:my-0">
                        <span
                            class="inline-flex rounded-full border
                                   border-white/15 bg-white/10 px-4 py-2
                                   text-xs font-semibold uppercase
                                   tracking-widest text-[#A8E2D7]"
                        >
                            Estrutura conectada
                        </span>

                        <h1
                            class="mt-6 max-w-lg text-4xl font-bold
                                   leading-tight tracking-tight sm:text-5xl"
                        >
                            Projetos organizados. Decisões rastreáveis.
                        </h1>

                        <p class="mt-6 max-w-lg text-lg leading-8 text-slate-200">
                            Gestão integrada do planejamento à evolução do
                            software, com informações organizadas, colaboração
                            e processos sob controle.
                        </p>
                    </div>

                    <div
                        class="hidden items-center justify-between text-xs
                               text-slate-300 lg:flex"
                    >
                        <span>SGP</span>
                        <span>Release MVP 1.0.0</span>
                    </div>
                </div>
            </section>

            <section
                class="flex min-h-[46vh] items-center justify-center
                       px-6 py-12 sm:px-10 lg:min-h-screen"
            >
                <div class="w-full max-w-lg">
                    <div
                        class="rounded-2xl border border-[#DCE3E7] bg-white
                               p-8 shadow-sm sm:p-10"
                    >
                        <div class="flex items-center gap-4">
                            <img
                                src="{{ asset('images/sgp-logo.png') }}"
                                alt="Símbolo do SGP"
                                class="h-16 w-16 rounded-2xl"
                            >

                            <div>
                                <p
                                    class="text-xs font-semibold uppercase
                                           tracking-widest text-[#287EA1]"
                                >
                                    Ambiente de gestão
                                </p>
                                <h2
                                    class="mt-1 text-3xl font-bold
                                           tracking-tight text-[#24313A]"
                                >
                                    Bem-vinda ao SGP
                                </h2>
                            </div>
                        </div>

                        <p class="mt-6 text-base leading-7 text-[#667680]">
                            Centralize projetos, requisitos, tarefas,
                            documentos, prazos e histórico em um único ambiente
                            seguro e rastreável.
                        </p>

                        <div class="mt-7 grid gap-3 text-sm text-[#24313A]">
                            @foreach ([
                                'Dashboard e indicadores reais',
                                'Kanban, calendário e cronograma Gantt',
                                'Documentação versionada em DOCX e PDF',
                            ] as $feature)
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-7 w-7 flex-none
                                               items-center justify-center
                                               rounded-full bg-[#E4F3F0]
                                               text-[#2E8B74]"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="m5 13 4 4L19 7"
                                            />
                                        </svg>
                                    </span>
                                    <span>{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>

                        @auth
                            <a
                                href="{{ route('dashboard') }}"
                                class="sgp-button-primary mt-8"
                            >
                                Ir para o painel
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="sgp-button-primary mt-8"
                            >
                                Acessar o sistema
                            </a>
                        @endauth

                        <p
                            class="mt-6 text-center text-xs leading-5
                                   text-[#667680]"
                        >
                            SGP • Sistema de Gestão de Projetos de Software<br>
                            Release MVP 1.0.0
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
