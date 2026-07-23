<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }} · {{ $project->name }}</p>
            <h1 class="text-xl font-bold text-[#24313A]">Editar {{ $requirement->code }}</h1>
            <p class="mt-1 text-sm text-[#667680]">Versão atual: {{ $requirement->current_version }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @include('requirements._project-nav')
        <form method="POST" action="{{ route('projects.requirements.update', [$project, $requirement]) }}" class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('requirements._form')
        </form>
    </div>
</x-app-layout>
