<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Editar organização</h1>
            <p class="mt-1 text-sm text-[#667680]">Administre os dados, o fuso e a disponibilidade do tenant</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('platform.organizations.update', $organization) }}">
                @include('platform.organizations._form')
            </form>
        </section>

        <section class="rounded-2xl border border-[#D7E6EA] bg-[#F2F8FA] p-5 text-sm text-[#36525E]">
            <p class="font-semibold">Separação de responsabilidades</p>
            <p class="mt-1">A situação da organização é controlada pela Administração da Plataforma. A equipe e seus níveis de acesso são administrados dentro da própria organização.</p>
        </section>
    </div>
</x-app-layout>
