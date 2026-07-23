<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-semibold text-[#287EA1]">{{ $task->code }}</p><h1 class="text-xl font-bold text-[#24313A]">Editar tarefa</h1><p class="mt-1 text-sm text-[#667680]">{{ $project->name }}</p></div>
    </x-slot>
    <div class="space-y-5">
        @include('requirements._project-nav')
        <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            @include('tasks._form')
        </form>
    </div>
</x-app-layout>
