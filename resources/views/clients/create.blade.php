<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Novo cliente ou unidade</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre quem demanda ou recebe o projeto</p>
        </div>
    </x-slot>

    <section class="mx-auto max-w-4xl rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf
            @include('clients._form')
        </form>
    </section>
</x-app-layout>
