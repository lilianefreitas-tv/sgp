<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#24313A]">Modelos de documentos</h1>
                <p class="mt-1 text-sm text-[#667680]">Gerencie versões e personalizações dos artefatos automáticos</p>
            </div>
            <a href="{{ route('document-templates.create') }}" class="sgp-button-primary w-auto">Novo modelo</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#287EA1]">{{ $template->code }}</p>
                            <h2 class="mt-1 font-bold text-[#24313A]">{{ $template->name }}</h2>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $template->is_active ? 'bg-[#EDF8F5] text-[#256C5C]' : 'bg-[#F3F5F6] text-[#667680]' }}">{{ $template->is_active ? 'Ativo' : 'Inativo' }}</span>
                    </div>
                    <p class="mt-3 text-sm text-[#667680]">{{ $template->description ?: 'Sem descrição.' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-[#E6F0F3] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $template->type->label() }}</span>
                        <span class="rounded-full bg-[#F3F5F6] px-3 py-1 text-xs font-semibold text-[#667680]">v{{ $template->version }}</span>
                    </div>
                    <div class="mt-5 flex items-center justify-between border-t border-[#E8EDF0] pt-4">
                        <a href="{{ route('document-templates.edit', $template) }}" class="text-sm font-semibold text-[#1D5D73] hover:underline">Editar</a>
                        @if ($template->is_active)
                            <form method="POST" action="{{ route('document-templates.deactivate', $template) }}" onsubmit="return confirm('Inativar este modelo? Os documentos já gerados serão preservados.')">@csrf @method('PATCH')<button class="text-sm font-semibold text-[#C44B4B] hover:underline">Inativar</button></form>
                        @else
                            <form method="POST" action="{{ route('document-templates.reactivate', $template) }}">@csrf @method('PATCH')<button class="text-sm font-semibold text-[#2E8B74] hover:underline">Reativar</button></form>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
        {{ $templates->links() }}
    </div>
</x-app-layout>
