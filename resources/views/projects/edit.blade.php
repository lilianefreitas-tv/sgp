<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Editar projeto</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} • {{ $project->name }}</p>
        </div>
    </x-slot>

    <section class="mx-auto max-w-5xl rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf
            @method('PUT')
            @include('projects._form')
        </form>
    </section>
</x-app-layout>
