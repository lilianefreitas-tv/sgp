<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-[#287EA1]">{{ $documentTemplate->code }}</p>
            <h1 class="mt-1 text-xl font-bold text-[#24313A]">Editar modelo de documento</h1>
        </div>
    </x-slot>
    <form method="POST" action="{{ route('document-templates.update', $documentTemplate) }}" class="mx-auto max-w-4xl space-y-5">
        @csrf
        @method('PUT')
        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            @include('document-templates._form')
        </section>
        <div class="flex justify-end gap-3">
            <a href="{{ route('document-templates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] bg-white px-5 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Cancelar</a>
            <button class="sgp-button-primary w-auto">Salvar alterações</button>
        </div>
    </form>
</x-app-layout>
