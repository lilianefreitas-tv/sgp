<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta
            name="description"
            content="PRISMA SGP - Sistema de Gestão de Projetos de Software"
        >

        <title>PRISMA SGP</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link
            rel="icon"
            type="image/png"
            href="{{ asset('images/prisma-favicon.png') }}"
        >

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
            class="min-h-screen bg-[#F4F9FA]"
        >
            @include('layouts.navigation')

            <div class="lg:pl-72">
                <header
                    class="sgp-topbar sticky top-0 z-20 flex h-20 items-center
                           justify-between border-b bg-white/95 px-5
                           backdrop-blur sm:px-8"
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
                                    PRISMA SGP
                                </h1>

                                <p class="text-sm text-[#667680]">
                                    Gestão de Projetos de Software
                                </p>
                            @endisset
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden items-center gap-2 rounded-xl border border-[#DCE3E7] bg-[#F4F9FA] px-3 py-2 text-xs font-medium text-[#667680] md:flex">
                            <svg class="h-4 w-4 text-[#17A2B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
                            </svg>
                            {{ now()->format('d/m/Y') }}
                        </div>

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
                                {{ Auth::user()->global_profile->label() }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 items-center justify-center
                                   rounded-full bg-[#E4F3F0] text-sm
                                   font-bold uppercase text-[#185063]"
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
                    PRISMA SGP • Sistema de Gestão de Projetos de Software •
                    {{ config('sgp.release_label') }}
                </footer>
            </div>
        </div>
    </body>
</html>
