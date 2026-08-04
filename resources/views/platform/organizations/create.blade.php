<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Nova organização</h1>
            <p class="mt-1 text-sm text-[#667680]">Crie o espaço da empresa, defina o Administrador principal e provisione os modelos padrão</p>
        </div>
    </x-slot>

    <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('platform.organizations.store') }}">
            @include('platform.organizations._form')
        </form>
    </section>
</x-app-layout>
