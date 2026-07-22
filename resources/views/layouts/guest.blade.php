<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SGP') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap"
            rel="stylesheet"
        >

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="antialiased">
        <main class="sgp-login-page">
            <section class="sgp-login-brand" aria-label="Apresentação do SGP">
                <div class="relative z-10 flex items-center gap-4">
                    <x-application-logo
                        class="h-14 w-14 flex-none text-[#123B4A]"
                    />

                    <div>
                        <p class="text-2xl font-bold tracking-tight text-white">
                            SGP
                        </p>

                        <p class="text-sm text-slate-200">
                            Sistema de Gestão de Projetos de Software
                        </p>
                    </div>
                </div>

                <div class="relative z-10 max-w-xl">
                    <span
                        class="mb-6 inline-flex rounded-full border border-white/15
                               bg-white/10 px-4 py-2 text-xs font-semibold
                               uppercase tracking-widest text-[#A8E2D7]"
                    >
                        Estrutura conectada
                    </span>

                    <h1
                        class="max-w-lg text-4xl font-bold leading-tight
                               tracking-tight text-white xl:text-5xl"
                    >
                        Projetos organizados. Decisões rastreáveis.
                    </h1>

                    <p class="mt-6 max-w-lg text-lg leading-8 text-slate-200">
                        Gestão integrada do planejamento à evolução do software,
                        com informações organizadas e processos sob controle.
                    </p>
                </div>

                <div
                    class="relative z-10 flex items-center justify-between
                           text-xs text-slate-300"
                >
                    <span>SGP</span>
                    <span>Software em evolução</span>
                </div>
            </section>

            <section class="sgp-login-form-area">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <x-application-logo
                            class="h-12 w-12 text-[#123B4A]"
                        />

                        <div>
                            <p class="text-xl font-bold text-[#123B4A]">
                                SGP
                            </p>

                            <p class="text-xs text-[#667680]">
                                Gestão de Projetos de Software
                            </p>
                        </div>
                    </div>

                    {{ $slot }}
                </div>
            </section>
        </main>
    </body>
</html>