<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Novo projeto</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre a base documental e operacional do projeto</p>
        </div>
    </x-slot>

    <section class="mx-auto max-w-5xl rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        @if ($clients->isEmpty())
            <div class="mb-6 rounded-xl border border-[#F1D49B] bg-[#FFF8E8] px-4 py-3 text-sm text-[#805719]">
                Cadastre ao menos um cliente ou unidade demandante ativa antes de criar o projeto.
                <a href="{{ route('clients.create') }}" class="font-semibold underline">Cadastrar agora</a>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}">
            @csrf
            @include('projects._form')
        </form>
    </section>
</x-app-layout>
