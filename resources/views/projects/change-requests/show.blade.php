<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-bold text-[#24313A]">{{ $changeRequest->code }} · {{ $changeRequest->title }}</h1>
                    <span class="rounded-full bg-[#E8F3F6] px-3 py-1 text-xs font-bold text-[#1D5D73]">{{ $changeRequest->state->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
            </div>
            @can('update', $changeRequest)
                <a href="{{ route('projects.change-requests.edit', [$project, $changeRequest]) }}" class="sgp-button-primary w-auto px-4 py-2.5">Editar solicitação</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-5">
        @if(session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-[#F0C7C7] bg-[#FFF4F4] px-4 py-3 text-sm text-[#A53E3E]">
                <p class="font-semibold">Não foi possível concluir a operação.</p>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @include('requirements._project-nav')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Origem</p><p class="mt-2 font-semibold text-[#24313A]">{{ $changeRequest->origin->label() }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Urgência</p><p class="mt-2 font-semibold text-[#24313A]">{{ $changeRequest->urgency?->label() ?? 'Não definida' }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Solicitante</p><p class="mt-2 font-semibold text-[#24313A]">{{ $changeRequest->requester->name }}</p></article>
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Responsável pela análise</p><p class="mt-2 font-semibold text-[#24313A]">{{ $changeRequest->analyst?->name ?? 'Não atribuído' }}</p></article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <article class="space-y-5 rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Descrição</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $changeRequest->description ?: 'Ainda não informada.' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Justificativa</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $changeRequest->justification ?: 'Ainda não informada.' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Baseline de referência</p><p class="mt-2 text-sm text-[#24313A]">{{ $changeRequest->baseline ? 'v'.$changeRequest->baseline->version.' · '.$changeRequest->baseline->title : 'Não definida' }}</p></div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Itens potencialmente afetados</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($changeRequest->affectedItems as $item)
                            <span class="rounded-full border border-[#C9DCE4] bg-[#F5F9FB] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $item->code ? $item->code.' · ' : '' }}{{ $item->title }}</span>
                        @empty
                            <span class="text-sm text-[#82919A]">Nenhum item indicado.</span>
                        @endforelse
                    </div>
                </div>
            </article>

            <aside class="space-y-4 rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <div><h2 class="font-bold text-[#24313A]">Fluxo da solicitação</h2><p class="mt-1 text-sm text-[#667680]">Submissão, análise e decisão formal com histórico preservado.</p></div>

                @can('submit', $changeRequest)
                    <form method="POST" action="{{ route('projects.change-requests.submit', [$project, $changeRequest]) }}">@csrf<button class="sgp-button-primary w-full justify-center">Submeter para análise</button></form>
                @endcan

                @can('startAnalysis', $changeRequest)
                    <form method="POST" action="{{ route('projects.change-requests.start-analysis', [$project, $changeRequest]) }}" class="space-y-2 rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] p-4">
                        @csrf
                        <label for="analyst_id" class="sgp-field-label">Responsável pela análise</label>
                        <select id="analyst_id" name="analyst_id" class="sgp-input">
                            <option value="">Usar meu usuário</option>
                            @foreach($projectUsers as $userOption)<option value="{{ $userOption->id }}" @selected($changeRequest->analyst_id === $userOption->id)>{{ $userOption->name }}</option>@endforeach
                        </select>
                        <button class="sgp-button-primary w-full justify-center">Iniciar análise</button>
                    </form>
                @endcan

                @can('returnForAdjustment', $changeRequest)
                    <form method="POST" action="{{ route('projects.change-requests.return', [$project, $changeRequest]) }}" class="space-y-2 rounded-xl border border-[#F2D4BD] bg-[#FFF8F2] p-4">
                        @csrf
                        <label for="return_reason" class="sgp-field-label">Motivo da devolução</label>
                        <textarea id="return_reason" name="reason" rows="3" maxlength="2000" class="sgp-input" required></textarea>
                        <button class="inline-flex w-full justify-center rounded-lg border border-[#D78650] px-4 py-2.5 text-sm font-semibold text-[#A65320] hover:bg-[#FFF0E8]">Devolver para ajustes</button>
                    </form>
                @endcan

                @can('decide', $changeRequest)
                    <form method="POST" action="{{ route('projects.change-requests.approve', [$project, $changeRequest]) }}" class="space-y-3 rounded-xl border p-4" style="border-color: #8fc8ba; background-color: #f7fcfa;" onsubmit="return confirm('Aprovar formalmente esta solicitação de mudança?')">
                        @csrf
                        <div>
                            <p class="font-bold" style="color: #174f43;">Decisão favorável</p>
                            <p class="mt-1 text-xs" style="color: #456b63;">A aprovação ficará registrada permanentemente no histórico.</p>
                        </div>
                        <label for="approval_reason" class="sgp-field-label" style="color: #24313a;">Parecer obrigatório</label>
                        <textarea id="approval_reason" name="reason" rows="3" maxlength="2000" class="sgp-input" required placeholder="Registre a fundamentação da decisão."></textarea>
                        <button class="inline-flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-bold transition" style="background-color: #1f6b59; color: #ffffff;">Aprovar solicitação</button>
                    </form>

                    <form method="POST" action="{{ route('projects.change-requests.reject', [$project, $changeRequest]) }}" class="space-y-2 rounded-xl border border-[#F0C7C7] bg-[#FFF8F8] p-4" onsubmit="return confirm('Rejeitar formalmente esta solicitação de mudança?')">
                        @csrf
                        <label for="rejection_reason" class="sgp-field-label">Motivo da rejeição</label>
                        <textarea id="rejection_reason" name="reason" rows="3" maxlength="2000" class="sgp-input" required placeholder="Registre a fundamentação da decisão."></textarea>
                        <button class="inline-flex w-full justify-center rounded-lg border border-[#E1AAAA] px-4 py-2.5 text-sm font-semibold text-[#A53E3E] hover:bg-[#FFF1F1]">Rejeitar solicitação</button>
                    </form>
                @endcan

                @if($changeRequest->state === \App\Enums\ChangeRequestState::UnderAnalysis
                    && $changeRequest->currentImpactAnalysis?->status !== \App\Enums\ChangeRequestAnalysisStatus::Completed)
                    <p class="rounded-xl border border-[#E8D59B] bg-[#FFF9E8] p-4 text-sm font-medium text-[#765A00]">Conclua a análise de impacto para liberar a decisão final.</p>
                @endif

                @can('cancel', $changeRequest)
                    <form method="POST" action="{{ route('projects.change-requests.cancel', [$project, $changeRequest]) }}" class="space-y-2 rounded-xl border border-[#F0C7C7] bg-[#FFF8F8] p-4" onsubmit="return confirm('Cancelar esta solicitação? O histórico será preservado.')">
                        @csrf
                        <label for="cancel_reason" class="sgp-field-label">Motivo do cancelamento</label>
                        <textarea id="cancel_reason" name="reason" rows="3" maxlength="2000" class="sgp-input" required></textarea>
                        <button class="inline-flex w-full justify-center rounded-lg border border-[#E1AAAA] px-4 py-2.5 text-sm font-semibold text-[#A53E3E] hover:bg-[#FFF1F1]">Cancelar solicitação</button>
                    </form>
                @endcan

                @if($changeRequest->state === \App\Enums\ChangeRequestState::Approved)
                    <p class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] p-4 text-sm font-medium text-[#256C5C]">Solicitação aprovada. A implementação e a nova baseline serão tratadas no P07.3.</p>
                @elseif($changeRequest->state === \App\Enums\ChangeRequestState::Rejected)
                    <p class="rounded-xl border border-[#F0C7C7] bg-[#FFF4F4] p-4 text-sm font-medium text-[#A53E3E]">Solicitação rejeitada. A decisão está encerrada e preservada no histórico.</p>
                @elseif($changeRequest->state === \App\Enums\ChangeRequestState::Implemented)
                    <p class="rounded-xl bg-[#F5F7F9] p-4 text-sm text-[#667680]">A implementação será habilitada no P07.3.</p>
                @endif
            </aside>
        </section>

        @include('projects.change-requests._impact-analysis')

        <section class="grid gap-5 xl:grid-cols-[minmax(320px,1fr)_minmax(0,2fr)]">
            @can('manageAttachments', $changeRequest)
                <article class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                    <h2 class="font-bold text-[#24313A]">Adicionar anexo ou evidência</h2>
                    <p class="mt-1 text-sm text-[#667680]">O arquivo ficará em armazenamento privado.</p>
                    <form method="POST" action="{{ route('projects.change-requests.attachments.store', [$project, $changeRequest]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <div><label for="attachment_kind" class="sgp-field-label">Categoria</label><select id="attachment_kind" name="attachment_kind" class="sgp-input" required><option value="attachment">Anexo</option><option value="evidence">Evidência</option></select></div>
                        <div><label for="file" class="sgp-field-label">Arquivo</label><input id="file" name="file" type="file" class="mt-1 block w-full rounded-lg border border-[#C9D3D9] bg-white px-3 py-2 text-sm" required><p class="mt-1 text-xs text-[#82919A]">Máximo {{ number_format($maxUploadMb, 0, ',', '.') }} MB. {{ implode(', ', $allowedExtensions) }}.</p></div>
                        <div><label for="attachment_description" class="sgp-field-label">Descrição</label><textarea id="attachment_description" name="description" rows="3" maxlength="300" class="sgp-input"></textarea></div>
                        <button class="sgp-button-primary w-full justify-center">Vincular arquivo</button>
                    </form>
                </article>
            @endcan

            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm {{ auth()->user()->cannot('manageAttachments', $changeRequest) ? 'xl:col-span-2' : '' }}">
                <div class="border-b border-[#DCE3E7] px-6 py-5"><h2 class="font-bold text-[#24313A]">Anexos e evidências</h2><p class="mt-1 text-sm text-[#667680]">Downloads sujeitos à autorização do projeto.</p></div>
                <div class="divide-y divide-[#E8EDF0]">
                    @forelse($changeRequest->attachments as $attachment)
                        <div class="flex flex-wrap items-start justify-between gap-4 px-6 py-4">
                            <div><p class="font-semibold text-[#24313A]">{{ $attachment->original_name }}</p><p class="mt-1 text-xs text-[#667680]">{{ $attachment->attachment_kind === 'evidence' ? 'Evidência' : 'Anexo' }} · {{ $attachment->formattedSize() }} · {{ $attachment->uploader->name }}</p>@if($attachment->description)<p class="mt-2 text-sm text-[#667680]">{{ $attachment->description }}</p>@endif</div>
                            <div class="flex gap-2">
                                <a href="{{ route('projects.change-requests.attachments.download', [$project, $changeRequest, $attachment]) }}" class="inline-flex rounded-lg border border-[#287EA1] px-3 py-2 text-xs font-semibold text-[#287EA1]">Baixar</a>
                                @can('manageAttachments', $changeRequest)<form method="POST" action="{{ route('projects.change-requests.attachments.destroy', [$project, $changeRequest, $attachment]) }}" onsubmit="return confirm('Remover este anexo da consulta?')">@csrf @method('DELETE')<button class="inline-flex rounded-lg border border-[#E6B8B8] px-3 py-2 text-xs font-semibold text-[#A53E3E]">Remover</button></form>@endcan
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-[#82919A]">Nenhum arquivo vinculado.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5"><h2 class="font-bold text-[#24313A]">Histórico imutável</h2><p class="mt-1 text-sm text-[#667680]">Cada transição é preservada com estado, responsável, data e motivo.</p></div>
            <ol class="divide-y divide-[#E8EDF0]">
                @foreach($changeRequest->transitions as $transition)
                    <li class="grid gap-2 px-6 py-4 md:grid-cols-[170px_minmax(0,1fr)_180px] md:items-start">
                        <time class="text-sm text-[#667680]">{{ $transition->occurred_at->format('d/m/Y H:i') }}</time>
                        <div><p class="font-semibold text-[#24313A]">{{ $transition->from_state?->label() ?? 'Registro inicial' }} → {{ $transition->to_state->label() }}</p>@if($transition->reason)<p class="mt-1 text-sm text-[#667680]">{{ $transition->reason }}</p>@endif</div>
                        <p class="text-sm font-medium text-[#667680] md:text-right">{{ $transition->actor->name }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-app-layout>
