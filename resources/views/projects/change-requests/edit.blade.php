<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Editar {{ $changeRequest->code }}</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('projects.change-requests.update', [$project, $changeRequest]) }}">
        @csrf
        @method('PUT')
        @include('projects.change-requests._form')
    </form>
</x-app-layout>
