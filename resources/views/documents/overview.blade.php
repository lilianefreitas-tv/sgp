<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Documentos</h1>
            <p class="mt-1 text-sm text-[#667680]">Gere e consulte os artefatos dos projetos aos quais você tem acesso</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('documents.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="search" class="sgp-field-label">Pesquisar projeto</label>
                    <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Código ou nome do projeto">
                </div>
                <button class="sgp-button-primary sm:w-auto">Pesquisar</button>
                <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] bg-white px-5 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar filtros</a>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @if ($projects->isEmpty())
                <div class="col-span-full rounded-2xl border border-dashed border-[#CBD5DA] bg-white px-6 py-14 text-center">
                    <p class="font-bold text-[#24313A]">Nenhum projeto encontrado</p>
                    <p class="mt-1 text-sm text-[#667680]">Os projetos acessíveis aparecerão aqui.</p>
                </div>
            @else
            @foreach ($projects as $project)
                <a href="{{ route('projects.documents.index', $project) }}" class="group rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#287EA1] hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#287EA1]">{{ $project->code }}</p>
                            <h2 class="mt-1 truncate font-bold text-[#24313A]">{{ $project->name }}</h2>
                            <p class="mt-2 text-sm text-[#667680]">{{ $project->client?->name ?? 'Sem demandante vinculado' }}</p>
                        </div>
                        <span class="rounded-full bg-[#E6F0F3] px-3 py-1 text-xs font-bold text-[#1D5D73]">{{ $project->documents_count }}</span>
                    </div>
                    <div class="mt-5 flex items-center justify-between border-t border-[#E8EDF0] pt-4">
                        <span class="text-xs text-[#667680]">Responsável: {{ $project->manager->name }}</span>
                        <span class="font-bold text-[#287EA1] transition group-hover:translate-x-1">→</span>
                    </div>
                </a>
            @endforeach
            @endif
        </section>

        {{ $projects->links() }}
    </div>
</x-app-layout>
