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

    <body class="font-sans antialiased">
        <div
            x-data="{ sidebarOpen: false }"
            class="min-h-screen bg-[#F5F7F9]"
        >
            @include('layouts.navigation')

            <div class="lg:pl-72">
                <header
                    class="sticky top-0 z-20 flex h-20 items-center
                           justify-between border-b border-[#DCE3E7]
                           bg-white px-5 sm:px-8"
                >
                    <div class="flex min-w-0 items-center gap-4">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-[#667680]
                                   hover:bg-[#F5F7F9] lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Abrir menu"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <div class="min-w-0">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h1
                                    class="truncate text-xl font-bold
                                           text-[#24313A]"
                                >
                                    SGP
                                </h1>

                                <p class="text-sm text-[#667680]">
                                    Gestão de Projetos de Software
                                </p>
                            @endisset
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p
                                class="max-w-48 truncate text-sm font-semibold
                                       text-[#24313A]"
                            >
                                {{ Auth::user()->name }}
                            </p>

                            <p
                                class="max-w-48 truncate text-xs
                                       text-[#667680]"
                            >
                                {{ Auth::user()->email }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 items-center justify-center
                                   rounded-full bg-[#E4F3F0] text-sm
                                   font-bold uppercase text-[#123B4A]"
                            title="{{ Auth::user()->name }}"
                        >
                            {{ mb_substr(Auth::user()->name, 0, 1) }}
                        </div>
                    </div>
                </header>

                <main class="p-5 sm:p-8">
                    {{ $slot }}
                </main>

                <footer
                    class="border-t border-[#DCE3E7] bg-white
                           px-5 py-4 text-center text-xs text-[#667680]
                           sm:px-8"
                >
                    SGP • Sistema de Gestão de Projetos de Software •
                    Versão 1.0 em desenvolvimento
                </footer>
            </div>
        </div>
    </body>
</html>