<x-app-layout>
    <x-slot name="header">{{ $artifact->code }} · {{ $artifact->title }}</x-slot>

    <div class="mx-auto max-w-6xl space-y-6 py-8">
        @if (session('success'))
            <div class="rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 p-4 text-red-800">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">{{ $artifact->type->label() }} · Revisão {{ $artifact->current_revision_sequence }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ $artifact->title }}</h1>
                    <p class="mt-2 text-slate-600">{{ $artifact->description }}</p>
                </div>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-sm font-semibold text-cyan-800">{{ $artifact->workflow_state->label() }}</span>
            </div>
            <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
                Aplicabilidade: <strong>{{ $applicability->outcome->label() }}</strong>. {{ $applicability->safeExplanation }}
            </div>
            <p class="mt-3 text-sm text-slate-500">Pertence a: <strong>{{ $artifact->initiative_id ? 'Iniciativa' : 'Projeto' }} · {{ $artifact->initiative?->code ?? $artifact->project?->code }} · {{ $artifact->initiative?->title ?? $artifact->project?->name }}</strong></p>
        </section>

        @include('artifacts.publications')

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Papéis documentais</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($assignments as $assignment)
                        <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span>{{ $assignment->user->name }}</span><strong>{{ $assignment->role->label() }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum papel atribuído.</p>
                    @endforelse
                </div>
                <form class="mt-5 grid gap-3" method="post" action="{{ route('artifacts.workflow.assignments.store', $artifact) }}">
                    @csrf
                    <select name="user_id" required class="rounded-lg border-slate-300">
                        <option value="">Selecione o usuário</option>
                        @foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
                    </select>
                    <select name="role" required class="rounded-lg border-slate-300">
                        @foreach ($documentRoles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white">Atribuir papel</button>
                </form>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Workflow da revisão atual</h2>
                @php
                    $isReviewer = $activeDocumentRoles->contains(\App\Enums\DocumentRole::Reviewer);
                    $isApprover = $activeDocumentRoles->contains(\App\Enums\DocumentRole::Approver);
                @endphp
                @if (! $artifact->archived_at && in_array($artifact->workflow_state, [\App\Enums\ArtifactWorkflowState::Draft, \App\Enums\ArtifactWorkflowState::ChangesRequested], true))
                    <form class="mt-4 space-y-3" method="post" action="{{ route('artifacts.workflow.submit', $artifact) }}">
                        @csrf
                        <textarea name="justification" required maxlength="10000" class="h-24 w-full rounded-lg border-slate-300" placeholder="Justificativa da submissão"></textarea>
                        <button class="rounded-lg bg-cyan-700 px-4 py-2 text-white">Submeter para análise</button>
                    </form>
                @elseif ($artifact->workflow_state === \App\Enums\ArtifactWorkflowState::InReview)
                    @php($openRound = $artifact->workflowRounds->firstWhere('state', \App\Enums\ArtifactWorkflowState::InReview))
                    @if ($isReviewer)
                        <form class="mt-4 space-y-3" method="post" action="{{ route('artifacts.workflow.decide', $openRound) }}">
                            @csrf
                            <select name="decision" required class="w-full rounded-lg border-slate-300">
                                <option value="forwarded_for_approval">Encaminhar para aprovação</option>
                                <option value="changes_requested">Solicitar ajustes</option>
                            </select>
                            <textarea name="justification" required maxlength="10000" class="h-24 w-full rounded-lg border-slate-300" placeholder="Parecer da revisão"></textarea>
                            <button class="rounded-lg bg-cyan-700 px-4 py-2 text-white">Concluir revisão técnica</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-slate-600">Aguardando análise do revisor responsável.</p>
                    @endif
                @elseif ($artifact->workflow_state === \App\Enums\ArtifactWorkflowState::AwaitingApproval)
                    @php($openRound = $artifact->workflowRounds->firstWhere('state', \App\Enums\ArtifactWorkflowState::AwaitingApproval))
                    @if ($isApprover)
                        <form class="mt-4 space-y-3" method="post" action="{{ route('artifacts.workflow.decide', $openRound) }}">
                            @csrf
                            <select name="decision" required class="w-full rounded-lg border-slate-300">
                                <option value="approved">Aprovar</option>
                                <option value="rejected">Rejeitar</option>
                                <option value="changes_requested">Solicitar ajustes</option>
                            </select>
                            <textarea name="justification" required maxlength="10000" class="h-24 w-full rounded-lg border-slate-300" placeholder="Fundamentação da decisão"></textarea>
                            <button class="rounded-lg bg-cyan-700 px-4 py-2 text-white">Registrar decisão de aprovação</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-slate-600">Revisão técnica concluída. Aguardando decisão do aprovador.</p>
                    @endif
                @else
                    <p class="mt-4 text-sm text-slate-600">A rodada atual está encerrada. Registre uma nova revisão para iniciar outro ciclo.</p>
                @endif
                @if ($latestApproved)
                    <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                        Última aprovação: revisão {{ $latestApproved->revision->sequence }}, checksum {{ $latestApproved->revision->checksum }}, em {{ $latestApproved->closed_at }}.
                    </div>
                @endif
            </section>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Rodadas e decisões</h2>
            <div class="mt-4 space-y-4">
                @forelse ($artifact->workflowRounds as $round)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex justify-between gap-3"><strong>Rodada {{ $round->sequence }} · Revisão {{ $round->revision->sequence }}</strong><span>{{ $round->state->label() }}</span></div>
                        <p class="mt-1 text-xs text-slate-500">Aplicabilidade: {{ $round->applicability_outcome }} · {{ $round->applicability_reason_code }}</p>
                        @foreach ($round->decisions as $decision)
                            <div class="mt-3 border-t border-slate-100 pt-3 text-sm">
                                <strong>{{ $decision->decision->label() }}</strong> por {{ $decision->actor->name }} como {{ $decision->role->label() }}
                                <p class="mt-1 text-slate-600">{{ $decision->justification }}</p>
                            </div>
                        @endforeach
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Este artefato ainda não possui rodada documental.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Histórico imutável de conteúdo</h2>
            @foreach ($artifact->revisions as $revision)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <strong>Revisão {{ $revision->sequence }}</strong> · {{ $revision->recorded_at }}
                    <p class="text-sm text-slate-600">{{ $revision->change_reason }} · checksum {{ $revision->checksum }}</p>
                    <pre class="mt-2 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($revision->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endforeach
        </section>

        @if (! $artifact->archived_at && ! in_array($artifact->workflow_state, [\App\Enums\ArtifactWorkflowState::InReview, \App\Enums\ArtifactWorkflowState::AwaitingApproval], true))
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Nova revisão</h2>
                <form method="post" action="{{ route('artifacts.revisions.store', $artifact) }}" class="mt-4 space-y-3">@csrf
                    <textarea name="summary" class="h-20 w-full rounded-lg border-slate-300" placeholder="Resumo executivo">{{ old('summary') }}</textarea>
                    <textarea name="objective" class="h-20 w-full rounded-lg border-slate-300" placeholder="Objetivo">{{ old('objective') }}</textarea>
                    <textarea name="scope" class="h-20 w-full rounded-lg border-slate-300" placeholder="Escopo">{{ old('scope') }}</textarea>
                    <textarea name="body" class="h-32 w-full rounded-lg border-slate-300" placeholder="Conteúdo da nova revisão em linguagem normal">{{ old('body') }}</textarea>
                    <details class="rounded-lg border border-slate-200 p-3"><summary class="cursor-pointer text-sm font-semibold text-cyan-800">Área técnica avançada</summary><div class="mt-3 space-y-3"><textarea name="content" class="h-24 w-full rounded-lg border-slate-300 font-mono text-xs" placeholder="JSON técnico opcional">{{ old('content') }}</textarea><textarea name="metadata" class="h-20 w-full rounded-lg border-slate-300 font-mono text-xs" placeholder="Metadados JSON opcionais">{{ old('metadata') }}</textarea></div></details>
                    <input type="number" name="schema_version" value="1" min="1" max="65535" required class="rounded-lg border-slate-300">
                    <input name="change_reason" required maxlength="10000" placeholder="Motivo da nova revisão" class="w-full rounded-lg border-slate-300">
                    <button class="rounded-lg bg-indigo-700 px-4 py-2 text-white">Registrar revisão</button>
                </form>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="post" action="{{ route('artifacts.archive', $artifact) }}">@csrf @method('patch')
                    <input name="archive_reason" required maxlength="10000" placeholder="Motivo do arquivamento" class="rounded-lg border-slate-300">
                    <button class="ml-2 rounded-lg bg-slate-700 px-4 py-2 text-white">Arquivar</button>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>
