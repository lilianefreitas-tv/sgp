<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-bold text-[#24313A]">Documentos</h1><p class="mt-1 text-sm text-[#667680]">{{ $parent->title ?? $parent->name }}</p></div></x-slot>
    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="sgp-page-intro">
            <p class="sgp-page-kicker">Engenharia documental adaptativa</p>
            <h2 class="mt-2 text-2xl font-bold">Os registros do trabalho viram documentos úteis</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">O SGP consolida os dados já registrados, preserva versões e evita que você escreva a mesma informação duas vezes.</p>
        </section>

        @if ($parentType === 'initiative')
            <section class="sgp-card p-5 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="sgp-page-kicker text-[#1D7A78]">Documento principal da iniciativa</p>
                        <h2 class="mt-2 text-xl font-bold text-[#24313A]">Dossiê da Iniciativa</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-[#667680]">Reúne identificação, configuração, levantamentos, propostas, negociações e conversão em projeto. O conteúdo é produzido a partir da Iniciativa e da Jornada Comercial.</p>
                        <p class="mt-3 text-xs font-medium text-[#52656F]">{{ $dossier ? 'Última consolidação: revisão '.$dossier->current_revision_sequence.'.' : 'Ainda não há dossiê consolidado.' }}</p>
                    </div>
                    <form method="post" action="{{ route('initiatives.documents.dossier', $parent) }}">@csrf<button class="sgp-button-primary whitespace-nowrap" type="submit">{{ $dossier ? 'Abrir dossiê atualizado' : 'Gerar dossiê' }}</button></form>
                </div>
            </section>
        @else
            <section class="rounded-xl border border-cyan-100 bg-cyan-50 p-5 text-sm leading-6 text-[#36535F]">
                <strong>Documento de Visão preservado.</strong> O Documento de Visão oficial do projeto continua no módulo Documentos do projeto e não é substituído por estes registros estruturados.
                <a class="ml-1 font-semibold text-[#1D5D73] underline" href="{{ route('projects.documents.index', $parent) }}">Abrir documentos do projeto</a>
            </section>
        @endif

        <section class="sgp-card overflow-hidden">
            <div class="border-b border-[#E8EDF0] px-5 py-4"><h2 class="sgp-section-heading">Acervo documental</h2><p class="sgp-section-description">{{ $artifacts->count() }} documento(s) neste contexto.</p></div>
            <div class="divide-y divide-[#E8EDF0]">
                @forelse ($artifacts as $artifact)
                    <a class="grid gap-3 px-5 py-5 transition hover:bg-[#FBFCFD] sm:grid-cols-[1fr_auto] sm:items-center" href="{{ route('artifacts.show', $artifact) }}">
                        <div><div class="flex flex-wrap gap-2"><span class="sgp-badge sgp-badge-neutral">{{ $artifact->code }}</span><span class="sgp-badge {{ $artifact->archived_at ? 'sgp-badge-warning' : 'sgp-badge-info' }}">{{ $artifact->archived_at ? 'Arquivado' : 'Ativo' }}</span></div><h3 class="mt-3 font-bold text-[#24313A]">{{ $artifact->title }}</h3>@if($artifact->description)<p class="mt-1 line-clamp-2 text-sm leading-6 text-[#667680]">{{ $artifact->description }}</p>@endif</div>
                        <div class="text-sm font-semibold text-[#1D5D73]">Revisão {{ $artifact->current_revision_sequence }} →</div>
                    </a>
                @empty
                    <div class="m-5 sgp-empty-state"><div class="sgp-empty-icon"><span class="text-lg">▤</span></div><h3 class="mt-4 font-bold text-[#24313A]">Nenhum documento ainda</h3><p class="mt-2 max-w-sm text-sm leading-6 text-[#667680]">{{ $parentType === 'initiative' ? 'Gere o dossiê usando os registros já existentes.' : 'Use a área avançada somente se precisar de um registro complementar.' }}</p></div>
                @endforelse
            </div>
        </section>

        <details class="sgp-card overflow-hidden">
            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-[#1D5D73]">Documento avulso ou integração técnica</summary>
            <div class="border-t border-[#E8EDF0] p-5">
                <p class="mb-5 text-sm leading-6 text-[#667680]">Uso excepcional para uma evidência complementar que não exista nos registros normais do SGP.</p>
                <form method="post" action="{{ $parentType === 'initiative' ? route('initiatives.artifacts.store', $parent) : route('projects.artifacts.store', $parent) }}" class="grid gap-4 lg:grid-cols-2">@csrf
                    <div><label class="sgp-field-label" for="type">Tipo</label><select id="type" name="type" required class="sgp-input">@foreach ($artifactTypes as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="sgp-field-label" for="title">Título</label><input id="title" name="title" value="{{ old('title') }}" required maxlength="255" class="sgp-input"></div>
                    <div class="lg:col-span-2"><label class="sgp-field-label" for="description">Descrição</label><textarea id="description" name="description" maxlength="10000" class="sgp-input min-h-20">{{ old('description') }}</textarea></div>
                    <div><label class="sgp-field-label" for="summary">Resumo</label><textarea id="summary" name="summary" class="sgp-input min-h-20">{{ old('summary') }}</textarea></div>
                    <div><label class="sgp-field-label" for="objective">Objetivo</label><textarea id="objective" name="objective" class="sgp-input min-h-20">{{ old('objective') }}</textarea></div>
                    <div class="lg:col-span-2"><label class="sgp-field-label" for="body">Conteúdo complementar</label><textarea id="body" name="body" class="sgp-input min-h-32">{{ old('body') }}</textarea></div>
                    <input type="hidden" name="scope" value=""><input type="hidden" name="metadata" value=""><input type="hidden" name="schema_version" value="1"><input type="hidden" name="change_reason" value="Registro documental complementar.">
                    <div class="lg:col-span-2"><button class="sgp-button-secondary" type="submit">Criar documento avulso</button></div>
                </form>
            </div>
        </details>
    </div>
</x-app-layout>
