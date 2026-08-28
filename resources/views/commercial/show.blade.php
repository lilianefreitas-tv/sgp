<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-bold text-[#24313A]">Jornada comercial</h1><p class="mt-1 text-sm text-[#667680]">{{ $initiative->code }} · {{ $initiative->title }}</p></div></x-slot>
    @php
        $stateLabels = ['open' => 'Aberta', 'identified' => 'Identificada', 'qualified' => 'Qualificada', 'under_discovery' => 'Em levantamento', 'under_proposal' => 'Em proposta', 'under_negotiation' => 'Em negociação', 'won' => 'Vencida', 'lost' => 'Perdida', 'suspended' => 'Suspensa'];
        $priorityLabels = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta'];
        $hasAssessment = $opportunity?->assessments->isNotEmpty() ?? false;
        $hasProposal = $opportunity?->proposals->isNotEmpty() ?? false;
        $acceptance = $opportunity?->negotiations->first(fn ($entry) => $entry->interaction_type === 'acceptance' && $entry->decision === 'Aceita' && $entry->proposal_version_id !== null);
        $isWon = $opportunity?->state === 'won';
        $contracts = $initiative->contracts;
        $commercialSteps = [
            ['label' => 'Iniciativa', 'done' => true, 'hint' => 'Origem comercial definida'],
            ['label' => 'Oportunidade', 'done' => $opportunity !== null, 'hint' => 'Prioridade e estimativa'],
            ['label' => 'Levantamento', 'done' => $hasAssessment, 'hint' => 'Necessidades e restrições'],
            ['label' => 'Proposta', 'done' => $hasProposal, 'hint' => 'Escopo e condições'],
            ['label' => 'Aceite', 'done' => $acceptance !== null, 'hint' => 'Versão exata aceita'],
            ['label' => 'Vitória', 'done' => $isWon, 'hint' => 'Pronta para conversão'],
        ];
    @endphp
    <div class="space-y-5">
        @if (session('success'))<div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-[#F1C7C7] bg-[#FFF4F4] px-4 py-3 text-sm text-[#9F3636]">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="sgp-page-intro">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><p class="sgp-page-kicker">{{ $initiative->code }} · Origem comercial</p><h2 class="mt-2 text-2xl font-bold">{{ $initiative->title }}</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">{{ $initiative->context ?: 'Sem contexto complementar registrado.' }}</p></div><div class="sgp-page-actions"><a class="sgp-button-secondary border border-white/20 bg-white/10 !text-white hover:!bg-white/20" href="{{ route('initiatives.index') }}">Ver iniciativa</a><a class="sgp-button-secondary border border-white/20 bg-white/10 !text-white hover:!bg-white/20" href="{{ route('commercial.index') }}">Voltar ao pipeline</a></div></div>
        </section>

        <section class="sgp-card overflow-hidden">
            <div class="border-b border-[#E8EDF0] px-5 py-4 sm:px-6"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="sgp-section-heading">Evolução da jornada</h2><p class="sgp-section-description">Siga as etapas na ordem para liberar a conversão em projeto.</p></div><span class="sgp-badge {{ $isWon && $acceptance ? 'sgp-badge-success' : 'sgp-badge-info' }}">{{ collect($commercialSteps)->where('done', true)->count() }} de {{ count($commercialSteps) }} concluídas</span></div></div>
            <ol class="grid divide-y divide-[#E8EDF0] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-6">
                @foreach($commercialSteps as $step)
                    <li class="flex gap-3 px-4 py-4"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $step['done'] ? 'bg-[#2E8B74] text-white' : 'bg-[#EAF0F2] text-[#667680]' }}">{{ $step['done'] ? '✓' : $loop->iteration }}</span><div><p class="text-sm font-bold text-[#24313A]">{{ $step['label'] }}</p><p class="mt-1 text-xs leading-5 text-[#667680]">{{ $step['hint'] }}</p></div></li>
                @endforeach
            </ol>
        </section>

        <section class="rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#287EA1]">Contratação, quando aplicável</p><h2 class="mt-1 font-bold text-[#123B4A]">Instrumentos vinculados à iniciativa</h2><p class="mt-1 text-sm text-[#667680]">O contrato é opcional. Quando existir, será herdado pelo projeto na conversão.</p></div>
                <a href="{{ route('contracts.create',['initiative'=>$initiative->id]) }}" class="sgp-button-primary sm:w-auto">Registrar contrato</a>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">@forelse($contracts as $contract)<a href="{{ route('contracts.show',$contract) }}" class="rounded-xl border border-white bg-white p-4 transition hover:border-[#287EA1]"><p class="text-xs font-semibold text-[#287EA1]">{{ $contract->code }} · {{ $contract->status->label() }}</p><p class="mt-1 font-bold text-[#24313A]">{{ $contract->title }}</p>@if($contract->project)<p class="mt-2 text-xs text-[#2E8B74]">Vinculado ao projeto {{ $contract->project->code }}</p>@endif</a>@empty<p class="text-sm text-[#667680]">Nenhum contrato registrado para esta iniciativa.</p>@endforelse</div>
        </section>

        @if(!$opportunity)
            <section class="sgp-card p-5 sm:p-7">
                <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <div><span class="sgp-badge sgp-badge-warning">Etapa inicial</span><h2 class="mt-3 text-xl font-bold text-[#24313A]">Cadastre a oportunidade</h2><p class="mt-2 text-sm leading-6 text-[#667680]">A oportunidade reúne prioridade, valor estimado e decisão esperada. Depois dela você poderá registrar levantamentos, propostas e negociações.</p></div>
                    <form method="POST" action="{{ route('commercial.opportunities.store',$initiative) }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <div class="sm:col-span-2"><label class="sgp-field-label" for="title">Título da oportunidade <span class="text-[#C44B4B]">*</span></label><input id="title" name="title" class="sgp-input" value="{{ old('title', $initiative->title) }}" maxlength="200" required></div>
                        <div><label class="sgp-field-label" for="priority">Prioridade</label><select id="priority" name="priority" class="sgp-input">@foreach($priorityLabels as $value => $label)<option value="{{ $value }}" @selected(old('priority','normal') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="sgp-field-label" for="expected_decision_at">Decisão esperada</label><input id="expected_decision_at" type="date" name="expected_decision_at" class="sgp-input" value="{{ old('expected_decision_at') }}"></div>
                        <div><label class="sgp-field-label" for="estimated_value">Valor estimado</label><input id="estimated_value" type="number" step="0.01" min="0" name="estimated_value" class="sgp-input" value="{{ old('estimated_value') }}" placeholder="0,00"></div>
                        <div class="sm:col-span-2"><label class="sgp-field-label" for="summary">Resumo comercial</label><textarea id="summary" name="summary" class="sgp-input min-h-24">{{ old('summary') }}</textarea></div>
                        <div class="sm:col-span-2 flex justify-end"><button class="sgp-button-primary sm:w-auto">Criar oportunidade</button></div>
                    </form>
                </div>
            </section>
        @else
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Oportunidade</p><p class="mt-2 font-bold text-[#24313A]">{{ $opportunity->code }}</p></div>
                <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Estado</p><span class="sgp-badge {{ $opportunity->state === 'won' ? 'sgp-badge-success' : ($opportunity->state === 'lost' ? 'sgp-badge-danger' : 'sgp-badge-info') }} mt-2">{{ $stateLabels[$opportunity->state] ?? $opportunity->state }}</span></div>
                <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Levantamentos</p><p class="sgp-stat-value">{{ $opportunity->assessments->count() }}</p></div>
                <div class="sgp-stat-card"><p class="text-xs font-semibold uppercase tracking-wide text-[#667680]">Propostas</p><p class="sgp-stat-value">{{ $opportunity->proposals->count() }}</p></div>
            </section>

            <section class="rounded-2xl border border-[#CFE2E7] bg-[#F1F8FA] p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#287EA1]">Próximo passo recomendado</p>
                        @if(!$hasAssessment)
                            <h2 class="mt-2 font-bold text-[#24313A]">Registre o levantamento inicial</h2><p class="mt-1 text-sm text-[#667680]">Documente necessidades e restrições antes de preparar a solução.</p>
                        @elseif(!$hasProposal)
                            <h2 class="mt-2 font-bold text-[#24313A]">Prepare a proposta comercial</h2><p class="mt-1 text-sm text-[#667680]">Consolide escopo, solução, valor e validade da proposta.</p>
                        @elseif(!$acceptance)
                            <h2 class="mt-2 font-bold text-[#24313A]">Registre o aceite da versão correta</h2><p class="mt-1 text-sm text-[#667680]">No histórico de negociações, escolha Aceite e vincule a versão aprovada.</p>
                        @elseif(!$isWon)
                            <h2 class="mt-2 font-bold text-[#24313A]">Marque a oportunidade como vencida</h2><p class="mt-1 text-sm text-[#667680]">Com o aceite registrado, atualize a etapa para Vencida.</p>
                        @else
                            <h2 class="mt-2 font-bold text-[#24313A]">Jornada comercial concluída</h2><p class="mt-1 text-sm text-[#667680]">A iniciativa está pronta para ser convertida em projeto.</p>
                        @endif
                    </div>
                    @if(!$hasAssessment)<a class="sgp-button-primary sm:w-auto" href="#levantamentos">Ir para levantamento</a>
                    @elseif(!$hasProposal)<a class="sgp-button-primary sm:w-auto" href="#propostas">Ir para proposta</a>
                    @elseif(!$acceptance)<a class="sgp-button-primary sm:w-auto" href="#negociacoes">Registrar aceite</a>
                    @elseif(!$isWon)<a class="sgp-button-primary sm:w-auto" href="#evolucao">Atualizar etapa</a>
                    @else<a class="sgp-button-primary sm:w-auto" href="{{ route('initiatives.conversion.show', $initiative) }}">Converter em projeto</a>@endif
                </div>
            </section>

            <section id="evolucao" class="sgp-card scroll-mt-24 p-5 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[1fr_1.2fr] lg:items-end"><div><h2 class="sgp-section-heading">Evoluir oportunidade</h2><p class="sgp-section-description">Registre cada mudança de etapa com sua justificativa.</p></div><form method="POST" action="{{ route('commercial.opportunities.transition',$opportunity) }}" class="grid gap-3 sm:grid-cols-[1fr_1.5fr_auto] sm:items-end">@csrf @method('PATCH')<div><label class="sgp-field-label" for="state">Próximo estado</label><select id="state" name="state" class="sgp-input">@foreach($stateLabels as $value => $label)@if($value !== 'identified')<option value="{{ $value }}">{{ $label }}</option>@endif @endforeach</select></div><div><label class="sgp-field-label" for="transition_justification">Justificativa</label><input id="transition_justification" name="justification" class="sgp-input" placeholder="Motivo da transição"></div><button class="sgp-button-primary sm:w-auto">Atualizar etapa</button></form></div>
            </section>

            <div class="grid gap-5 xl:grid-cols-3">
                <section id="levantamentos" class="sgp-card scroll-mt-24 p-5">
                    <h2 class="sgp-section-heading">Levantamentos</h2><p class="sgp-section-description">Necessidades e restrições conhecidas.</p>
                    <div class="mt-4 space-y-3">@forelse($opportunity->assessments as $assessment)<article class="rounded-xl bg-[#F8FAFB] p-3"><div class="flex justify-between gap-3"><strong class="text-sm text-[#24313A]">Levantamento #{{ $loop->iteration }}</strong><span class="sgp-badge sgp-badge-neutral">{{ ucfirst(str_replace('_',' ',$assessment->state)) }}</span></div>@if($assessment->needs)<p class="mt-2 text-xs leading-5 text-[#667680]">{{ $assessment->needs }}</p>@endif</article>@empty<p class="text-sm text-[#667680]">Nenhum levantamento registrado.</p>@endforelse</div>
                    <details class="mt-5 rounded-xl border border-[#DCE3E7] p-4" @if(!$hasAssessment) open @endif><summary class="cursor-pointer text-sm font-semibold text-[#1D5D73]">Adicionar levantamento</summary><form method="POST" action="{{ route('commercial.assessments.store',$opportunity) }}" class="mt-4 space-y-3">@csrf<select name="state" class="sgp-input"><option value="draft">Rascunho</option><option value="preparing">Em preparação</option><option value="performed">Realizado</option><option value="consolidated">Consolidado</option><option value="validated">Validado</option></select><textarea name="needs" class="sgp-input" placeholder="Necessidades identificadas"></textarea><textarea name="constraints" class="sgp-input" placeholder="Restrições"></textarea><button class="sgp-button-primary">Registrar levantamento</button></form></details>
                </section>

                <section id="propostas" class="sgp-card scroll-mt-24 p-5">
                    <h2 class="sgp-section-heading">Propostas</h2><p class="sgp-section-description">Escopo, solução e condições versionadas.</p>
                    <div class="mt-4 space-y-3">@forelse($opportunity->proposals as $proposal)<article class="rounded-xl bg-[#F8FAFB] p-3"><div class="flex justify-between gap-3"><strong class="text-sm text-[#24313A]">Proposta #{{ $loop->iteration }}</strong><span class="sgp-badge sgp-badge-info">{{ $proposal->versions->count() }} versão(ões)</span></div>@if($proposal->scope_summary)<p class="mt-2 text-xs leading-5 text-[#667680]">{{ $proposal->scope_summary }}</p>@endif</article>@empty<p class="text-sm text-[#667680]">Nenhuma proposta registrada.</p>@endforelse</div>
                    <details class="mt-5 rounded-xl border border-[#DCE3E7] p-4" @if($hasAssessment && !$hasProposal) open @endif><summary class="cursor-pointer text-sm font-semibold text-[#1D5D73]">Criar proposta</summary><form method="POST" action="{{ route('commercial.proposals.store',$opportunity) }}" class="mt-4 space-y-3">@csrf<textarea name="scope_summary" class="sgp-input" placeholder="Resumo do escopo"></textarea><textarea name="solution_summary" class="sgp-input" placeholder="Resumo da solução"></textarea><input name="pricing_model" class="sgp-input" placeholder="Modelo de precificação"><input type="number" step="0.01" min="0" name="estimated_value" class="sgp-input" placeholder="Valor estimado"><div><label class="sgp-field-label" for="validity_until">Validade da proposta</label><input id="validity_until" type="date" name="validity_until" class="sgp-input"></div><button class="sgp-button-primary">Criar proposta</button></form></details>
                </section>

                <section id="negociacoes" class="sgp-card scroll-mt-24 p-5">
                    <h2 class="sgp-section-heading">Negociações</h2><p class="sgp-section-description">Linha do tempo das interações comerciais.</p>
                    <div class="sgp-timeline mt-5">@forelse($opportunity->negotiations->sortByDesc('occurred_at') as $entry)<article class="sgp-timeline-item"><p class="text-xs font-semibold uppercase text-[#287EA1]">{{ ucfirst(str_replace('_',' ',$entry->interaction_type)) }}</p><p class="mt-1 text-sm font-semibold text-[#24313A]">{{ $entry->occurred_at?->format('d/m/Y H:i') }}</p><p class="mt-1 text-xs leading-5 text-[#667680]">{{ $entry->summary }}</p></article>@empty<p class="text-sm text-[#667680]">Nenhuma interação registrada.</p>@endforelse</div>
                    <details class="mt-5 rounded-xl border border-[#DCE3E7] p-4" @if($hasProposal && !$acceptance) open @endif><summary class="cursor-pointer text-sm font-semibold text-[#1D5D73]">Registrar interação</summary><form method="POST" action="{{ route('commercial.negotiations.store',$opportunity) }}" class="mt-4 space-y-3" x-data="{ type: '{{ old('interaction_type', 'meeting') }}' }">@csrf<select name="interaction_type" class="sgp-input" x-model="type"><option value="meeting">Reunião</option><option value="email">E-mail</option><option value="phone">Telefone</option><option value="counterproposal">Contraproposta</option><option value="internal_analysis">Análise interna</option><option value="decision">Decisão</option><option value="acceptance">Aceite de proposta</option></select><div x-show="type === 'acceptance'" x-cloak><label class="sgp-field-label" for="proposal_version_id">Versão aceita da proposta</label><select id="proposal_version_id" name="proposal_version_id" class="sgp-input" :required="type === 'acceptance'"><option value="">Selecione a versão exata</option>@foreach($opportunity->proposals as $proposal)@foreach($proposal->versions as $version)<option value="{{ $version->id }}">{{ $proposal->code }} · versão {{ $version->sequence }}@if($version->estimated_value) · R$ {{ number_format($version->estimated_value, 2, ',', '.') }}@endif</option>@endforeach @endforeach</select><p class="sgp-field-help">O aceite permanecerá vinculado a esta versão, mesmo que a proposta receba revisões futuras.</p></div><input type="datetime-local" name="occurred_at" class="sgp-input" required><textarea name="summary" class="sgp-input" placeholder="Resumo da interação"></textarea><input name="next_step" class="sgp-input" placeholder="Próximo passo"><button class="sgp-button-primary">Registrar interação</button></form></details>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
