<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Novo modelo de documento</h1>
            <p class="mt-1 text-sm text-[#667680]">Crie uma versão personalizada de um dos artefatos suportados</p>
        </div>
    </x-slot>
    <form method="POST" action="{{ route('document-templates.store') }}" class="mx-auto max-w-4xl space-y-5">
        @csrf
        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            @include('document-templates._form')
        </section>
        <div class="flex justify-end gap-3">
            <a href="{{ route('document-templates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] bg-white px-5 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Cancelar</a>
            <button class="sgp-button-primary w-auto">Cadastrar modelo</button>
        </div>
    </form>
</x-app-layout>
