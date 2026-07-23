<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-[#287EA1]">{{ $requirement->code }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $requirement->status->badgeClasses() }}">{{ $requirement->status->label() }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $requirement->priority->badgeClasses() }}">{{ $requirement->priority->label() }}</span>
                @unless ($requirement->is_active)<span class="rounded-full bg-[#F3F5F6] px-3 py-1 text-xs font-semibold text-[#667680]">Inativo</span>@endunless
            </div>
            <h1 class="mt-1 truncate text-xl font-bold text-[#24313A]">{{ $requirement->title }}</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @include('requirements._project-nav')

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('projects.requirements.index', $project) }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] bg-white px-4 py-2.5 text-sm font-semibold text-[#24313A] hover:bg-[#F5F7F9]">Voltar à lista</a>
            @if ($canManage)
                <a href="{{ route('projects.requirements.edit', [$project, $requirement]) }}" class="sgp-button-primary w-auto px-4 py-2.5">Editar requisito</a>
                @if ($requirement->is_active)
                    <form method="POST" action="{{ route('projects.requirements.deactivate', [$project, $requirement]) }}" onsubmit="return confirm('Inativar este requisito? O histórico continuará disponível.')">
                        @csrf @method('PATCH')
                        <button class="inline-flex rounded-lg border border-[#DCE3E7] bg-white px-4 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Inativar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('projects.requirements.reactivate', [$project, $requirement]) }}">
                        @csrf @method('PATCH')
                        <button class="inline-flex rounded-lg border border-[#2E8B74] bg-white px-4 py-2.5 text-sm font-semibold text-[#2E8B74] hover:bg-[#EDF8F5]">Reativar</button>
                    </form>
                @endif
            @endif
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Tipo</p><p class="mt-2 font-semibold text-[#24313A]">{{ $requirement->type->label() }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Responsável</p><p class="mt-2 font-semibold text-[#24313A]">{{ $requirement->responsible?->name ?? 'Não definido' }}</p><p class="mt-1 text-xs text-[#667680]">{{ $requirement->responsible?->email }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Origem</p><p class="mt-2 font-semibold text-[#24313A]">{{ $requirement->source ?: 'Não informada' }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Versão atual</p><p class="mt-2 text-2xl font-bold text-[#123B4A]">{{ $requirement->current_version }}</p><p class="mt-1 text-xs text-[#667680]">Atualizado em {{ $requirement->updated_at->format('d/m/Y H:i') }}</p></article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Descrição</h2>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $requirement->description ?: 'Nenhuma descrição detalhada foi informada.' }}</p>
            </article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Critérios de aceite</h2>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $requirement->acceptance_criteria ?: 'Nenhum critério de aceite foi informado.' }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="font-bold text-[#24313A]">Histórico de versões</h2>
                <p class="mt-1 text-sm text-[#667680]">As versões anteriores são preservadas automaticamente quando o requisito é alterado.</p>
            </div>
            <div class="divide-y divide-[#E8EDF0]">
                @forelse ($requirement->versions as $version)
                    <article class="px-6 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-[#24313A]">Versão {{ $version->version_number }} · {{ $version->title }}</p>
                            <p class="text-xs text-[#667680]">{{ $version->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <p class="mt-2 text-sm text-[#667680]">Alterada por {{ $version->changedBy->name }}</p>
                        @if ($version->change_reason)<p class="mt-2 rounded-lg bg-[#F8FAFB] px-3 py-2 text-sm text-[#24313A]"><span class="font-semibold">Motivo:</span> {{ $version->change_reason }}</p>@endif
                    </article>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-[#667680]">Este requisito ainda não possui versões anteriores.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
