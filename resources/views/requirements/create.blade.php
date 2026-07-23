<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }} · {{ $project->name }}</p>
            <h1 class="text-xl font-bold text-[#24313A]">Novo requisito</h1>
            <p class="mt-1 text-sm text-[#667680]">O código será gerado automaticamente ao salvar.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @include('requirements._project-nav')
        <form method="POST" action="{{ route('projects.requirements.store', $project) }}" class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            @csrf
            @include('requirements._form')
        </form>
    </div>
</x-app-layout>
