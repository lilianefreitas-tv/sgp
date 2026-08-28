<x-app-layout>
    <x-slot name="header">{{ $initiative->code }}</x-slot>
    <div class="mx-auto max-w-6xl space-y-5">
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        <section class="sgp-page-intro"><p class="sgp-page-kicker">{{ $initiative->origin->label() }} · {{ $initiative->state->label() }}</p><h1 class="mt-2 text-2xl font-bold">{{ $initiative->title }}</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">{{ $initiative->context ?: 'Contexto não informado.' }}</p><p class="mt-3 max-w-3xl text-xs text-white/70">{{ $initiative->origin->description() }}</p></section>
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,.6fr)]">
            <div class="space-y-5">
                <section class="sgp-card p-5"><h2 class="sgp-section-heading">Configuração vigente</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-[#667680]">Natureza</dt><dd class="mt-1">{{ $initiative->execution_nature->label() }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-[#667680]">Gestão financeira</dt><dd class="mt-1">{{ $initiative->financial_management_mode->label() }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-[#667680]">Nível de gestão</dt><dd class="mt-1">{{ $initiative->management_level->label() }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-[#667680]">Metodologia</dt><dd class="mt-1">{{ $initiative->methodology->label() }}</dd></div>
                </dl></section>
                <section class="sgp-card p-5"><div class="flex items-center justify-between gap-3"><div><h2 class="sgp-section-heading">Contratos vinculados</h2><p class="sgp-section-description">A origem “Contratação existente” exige ao menos um contrato antes da conversão.</p></div></div>
                    <div class="mt-4 space-y-3">@forelse ($initiative->contracts as $contract)<a class="block rounded-xl border border-[#DCE3E7] p-4 hover:bg-[#F7FAFB]" href="{{ route('contracts.show', $contract) }}"><span class="font-semibold">{{ $contract->code }} · {{ $contract->title }}</span><span class="ml-2 text-sm text-[#667680]">Versão {{ $contract->versions->max('version') }}</span></a>@empty<p class="text-sm text-[#667680]">Nenhum contrato vinculado.</p>@endforelse</div>
                    @if ($canManageLifecycle && ! $initiative->project && ! in_array($initiative->state, [\App\Enums\InitiativeState::Converted, \App\Enums\InitiativeState::Cancelled, \App\Enums\InitiativeState::Archived], true))
                        <form class="mt-5 rounded-xl bg-[#F1F7F8] p-4" method="post" action="{{ route('initiatives.contract.link', $initiative) }}">@csrf @method('patch')<input type="hidden" name="lock_version" value="{{ $initiative->lock_version }}"><label class="sgp-field-label" for="contract_id">Vincular contrato independente</label><select class="sgp-input" id="contract_id" name="contract_id" required><option value="">Selecione</option>@foreach ($availableContracts as $contract)<option value="{{ $contract->id }}">{{ $contract->code }} · {{ $contract->title }}</option>@endforeach</select><label class="sgp-field-label mt-3" for="contract_reason">Justificativa</label><textarea class="sgp-input min-h-20" id="contract_reason" name="justification" required></textarea><button class="sgp-button-primary mt-3" type="submit">Vincular e versionar</button></form>
                    @endif
                </section>
                <section class="sgp-card p-5"><h2 class="sgp-section-heading">Histórico de configuração</h2><div class="mt-4 space-y-3">@foreach ($initiative->configurationVersions as $version)<div class="rounded-xl border border-[#DCE3E7] p-4"><p class="font-semibold">Versão {{ $version->sequence }} {{ $version->superseded_at ? '· superada' : '· vigente' }}</p><p class="mt-1 text-sm text-[#667680]">{{ $version->justification }}</p></div>@endforeach</div></section>
            </div>
            <aside class="space-y-5">
                <section class="sgp-card p-5"><h2 class="sgp-section-heading">Ações</h2><p class="mt-3 text-sm text-[#667680]">{{ $availability['reason'] }}</p><div class="mt-4 grid gap-3">
                    @if ($initiative->project)<a class="sgp-button-primary" href="{{ route('projects.show', $initiative->project) }}">Abrir projeto</a>
                    @elseif ($availability['available'])<a class="sgp-button-primary" href="{{ route('initiatives.conversion.show', $initiative) }}">Iniciar projeto</a>@endif
                    @if ($canManageLifecycle && ! $initiative->project && ! in_array($initiative->state, [\App\Enums\InitiativeState::Converted, \App\Enums\InitiativeState::Cancelled, \App\Enums\InitiativeState::Archived], true))<a class="sgp-button-secondary" href="{{ route('initiatives.edit', $initiative) }}">Editar iniciativa</a>@endif
                    <a class="sgp-button-secondary" href="{{ route('initiatives.artifacts.index', $initiative) }}">Documentos</a>
                </div></section>
                @if ($canManageLifecycle && $initiative->state === \App\Enums\InitiativeState::Archived)
                    <form class="sgp-card p-5" method="post" action="{{ route('initiatives.restore', $initiative) }}">@csrf @method('patch')<input type="hidden" name="lock_version" value="{{ $initiative->lock_version }}"><label class="sgp-field-label" for="restore_reason">Justificativa da restauração</label><textarea class="sgp-input min-h-20" id="restore_reason" name="justification" required></textarea><button class="sgp-button-primary mt-3" type="submit">Restaurar como rascunho</button></form>
                @elseif ($canManageLifecycle && ! $initiative->project && $initiative->state !== \App\Enums\InitiativeState::Cancelled)
                    <section class="sgp-card p-5"><h2 class="sgp-section-heading">Encerrar sem excluir</h2><form class="mt-4" method="post" action="{{ route('initiatives.archive', $initiative) }}">@csrf @method('patch')<input type="hidden" name="lock_version" value="{{ $initiative->lock_version }}"><label class="sgp-field-label" for="archive_reason">Justificativa do arquivamento</label><textarea class="sgp-input min-h-20" id="archive_reason" name="justification" required></textarea><button class="sgp-button-secondary mt-3" type="submit">Arquivar</button></form><form class="mt-4 border-t border-[#E8EDF0] pt-4" method="post" action="{{ route('initiatives.cancel', $initiative) }}">@csrf @method('patch')<input type="hidden" name="lock_version" value="{{ $initiative->lock_version }}"><label class="sgp-field-label" for="cancel_reason">Justificativa do cancelamento</label><textarea class="sgp-input min-h-20" id="cancel_reason" name="justification" required></textarea><button class="mt-3 text-sm font-semibold text-red-700" type="submit">Cancelar definitivamente</button></form></section>
                @endif
            </aside>
        </div>
        <a class="sgp-link" href="{{ route('initiatives.index') }}">Voltar às iniciativas</a>
    </div>
</x-app-layout>
