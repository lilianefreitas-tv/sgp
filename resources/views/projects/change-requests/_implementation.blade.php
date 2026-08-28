@php
    $implementation = $changeRequest->implementation;
    $implementationEditable = auth()->user()->can('updateImplementation', $changeRequest);
    $contractDisposition = old(
        'contract_disposition',
        $implementation?->contract_disposition?->value ?? \App\Enums\ChangeRequestContractDisposition::NotApplicable->value,
    );
    $baselineDisposition = old(
        'baseline_disposition',
        $implementation?->baseline_disposition?->value ?? \App\Enums\ChangeRequestBaselineDisposition::CreateNew->value,
    );
@endphp

@if($implementation || in_array($changeRequest->state, [\App\Enums\ChangeRequestState::Approved, \App\Enums\ChangeRequestState::Implemented], true))
    <section id="change-implementation" class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#DCE3E7] px-6 py-5">
            <div>
                <h2 class="font-bold text-[#24313A]">Implementação da mudança</h2>
                <p class="mt-1 text-sm text-[#667680]">Execução controlada, tratamento contratual, evidências e destino de baseline.</p>
            </div>
            @if($implementation)
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $implementation->status === \App\Enums\ChangeRequestImplementationStatus::Completed ? 'bg-[#EDF8F5] text-[#256C5C]' : ($implementation->status === \App\Enums\ChangeRequestImplementationStatus::InProgress ? 'bg-[#E8F3F6] text-[#1D5D73]' : 'bg-[#FFF4D9] text-[#8A6400]') }}">
                    {{ $implementation->status->label() }}
                </span>
            @endif
        </div>

        @if($implementationEditable)
            <form method="POST" action="{{ route('projects.change-requests.implementation.update', [$project, $changeRequest]) }}" class="space-y-7 p-6">
                @csrf
                <div class="rounded-xl border border-[#C9DCE4] bg-[#F5F9FB] p-4 text-sm text-[#52616A]">
                    Salve o planejamento progressivamente. Para iniciar, informe plano, responsável, data-alvo e os tratamentos contratual e de baseline. Para concluir, registre execução, verificação e ao menos uma evidência.
                    <p class="mt-2 font-semibold"><span style="color: #b42318;">*</span> Obrigatório para iniciar ou concluir, conforme indicado.</p>
                </div>

                <div class="sgp-change-request-grid-three">
                    <div>
                        <label for="responsible_id" class="sgp-field-label">Responsável pela implementação <span aria-hidden="true" style="color: #b42318;">*</span></label>
                        <select id="responsible_id" name="responsible_id" class="sgp-input" @disabled(! $canManageProject)>
                            <option value="">Selecione</option>
                            @foreach($projectUsers as $userOption)
                                <option value="{{ $userOption->id }}" @selected((string) old('responsible_id', $implementation?->responsible_id) === (string) $userOption->id)>{{ $userOption->name }}</option>
                            @endforeach
                        </select>
                        @if(! $canManageProject && $implementation?->responsible_id)
                            <input type="hidden" name="responsible_id" value="{{ $implementation->responsible_id }}">
                        @endif
                    </div>
                    <div>
                        <label for="planned_start_date" class="sgp-field-label">Início planejado</label>
                        <input id="planned_start_date" name="planned_start_date" type="date" value="{{ old('planned_start_date', $implementation?->planned_start_date?->toDateString()) }}" class="sgp-input">
                    </div>
                    <div>
                        <label for="target_completion_date" class="sgp-field-label">Data-alvo <span aria-hidden="true" style="color: #b42318;">*</span></label>
                        <input id="target_completion_date" name="target_completion_date" type="date" value="{{ old('target_completion_date', $implementation?->target_completion_date?->toDateString()) }}" class="sgp-input">
                    </div>
                </div>

                <div>
                    <label for="plan_summary" class="sgp-field-label">Plano de implementação <span aria-hidden="true" style="color: #b42318;">*</span></label>
                    <textarea id="plan_summary" name="plan_summary" rows="4" maxlength="10000" class="sgp-input" placeholder="Descreva atividades, sequência, dependências, retorno e critérios de pronto.">{{ old('plan_summary', $implementation?->plan_summary) }}</textarea>
                </div>

                <div class="grid gap-5 xl:grid-cols-2">
                    <div>
                        <label for="execution_summary" class="sgp-field-label">Registro da execução <span aria-hidden="true" style="color: #b42318;">*</span></label>
                        <textarea id="execution_summary" name="execution_summary" rows="4" maxlength="10000" class="sgp-input" placeholder="Registre o que foi efetivamente implementado e eventuais desvios do plano.">{{ old('execution_summary', $implementation?->execution_summary) }}</textarea>
                        <p class="sgp-field-help">Obrigatório para concluir.</p>
                    </div>
                    <div>
                        <label for="verification_summary" class="sgp-field-label">Verificação da implementação <span aria-hidden="true" style="color: #b42318;">*</span></label>
                        <textarea id="verification_summary" name="verification_summary" rows="4" maxlength="10000" class="sgp-input" placeholder="Registre testes, resultados, ambiente, ressalvas e aceite técnico.">{{ old('verification_summary', $implementation?->verification_summary) }}</textarea>
                        <p class="sgp-field-help">Obrigatório para concluir, junto com uma evidência anexada.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] p-5">
                    <h3 class="font-bold text-[#24313A]">Tratamento contratual</h3>
                    <p class="mt-1 text-sm text-[#667680]">Aditivo somente é aplicável quando houver instrumento contratual e impacto aprovado.</p>
                    <div class="sgp-change-request-grid-three mt-5">
                        <div>
                            <label for="contract_disposition" class="sgp-field-label">Tratamento <span aria-hidden="true" style="color: #b42318;">*</span></label>
                            <select id="contract_disposition" name="contract_disposition" class="sgp-input">
                                @foreach($implementationContractDispositions as $value => $label)
                                    <option value="{{ $value }}" @selected($contractDisposition === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="implementation_contract_id" class="sgp-field-label">Contrato relacionado</label>
                            <select id="implementation_contract_id" name="contract_id" class="sgp-input">
                                <option value="">Selecione quando aplicável</option>
                                @foreach($project->contracts as $contract)
                                    <option value="{{ $contract->id }}" @selected((string) old('contract_id', $implementation?->contract_id) === (string) $contract->id)>{{ $contract->code }} · {{ $contract->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="amendment_effective_date" class="sgp-field-label">Vigência do aditivo</label>
                            <input id="amendment_effective_date" name="amendment_effective_date" type="date" value="{{ old('amendment_effective_date', $implementation?->amendment_effective_date?->toDateString()) }}" class="sgp-input">
                        </div>
                        <div>
                            <label for="amendment_reference" class="sgp-field-label">Referência do aditivo</label>
                            <input id="amendment_reference" name="amendment_reference" maxlength="160" value="{{ old('amendment_reference', $implementation?->amendment_reference) }}" class="sgp-input" placeholder="Número, processo ou instrumento">
                        </div>
                        <div class="xl:col-span-2">
                            <label for="contract_justification" class="sgp-field-label">Justificativa do tratamento <span aria-hidden="true" style="color: #b42318;">*</span></label>
                            <textarea id="contract_justification" name="contract_justification" rows="3" maxlength="10000" class="sgp-input">{{ old('contract_justification', $implementation?->contract_justification) }}</textarea>
                        </div>
                    </div>
                    <div class="mt-5">
                        <label for="amendment_summary" class="sgp-field-label">Descrição do aditivo</label>
                        <textarea id="amendment_summary" name="amendment_summary" rows="3" maxlength="10000" class="sgp-input" placeholder="Obrigatória quando o aditivo estiver formalizado.">{{ old('amendment_summary', $implementation?->amendment_summary) }}</textarea>
                    </div>
                </div>

                <div class="rounded-xl border border-[#C9DCE4] bg-[#F5F9FB] p-5">
                    <h3 class="font-bold text-[#24313A]">Destino de baseline</h3>
                    <p class="mt-1 text-sm text-[#667680]">A baseline anterior permanece imutável. Quando criada, a nova referência captura o estado vigente e registra esta RM como origem.</p>
                    <div class="sgp-change-request-grid-three mt-5">
                        <div>
                            <label for="baseline_disposition" class="sgp-field-label">Tratamento <span aria-hidden="true" style="color: #b42318;">*</span></label>
                            <select id="baseline_disposition" name="baseline_disposition" class="sgp-input">
                                @foreach($implementationBaselineDispositions as $value => $label)
                                    <option value="{{ $value }}" @selected($baselineDisposition === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label for="baseline_title" class="sgp-field-label">Título da nova baseline</label>
                            <input id="baseline_title" name="baseline_title" maxlength="160" value="{{ old('baseline_title', $implementation?->baseline_title) }}" class="sgp-input" placeholder="Ex.: Baseline após RM-001">
                        </div>
                    </div>
                    <div class="mt-5">
                        <label for="baseline_justification" class="sgp-field-label">Justificativa da baseline <span aria-hidden="true" style="color: #b42318;">*</span></label>
                        <textarea id="baseline_justification" name="baseline_justification" rows="3" maxlength="10000" class="sgp-input" placeholder="Justifique a criação da nova baseline ou a decisão de não constituí-la.">{{ old('baseline_justification', $implementation?->baseline_justification) }}</textarea>
                    </div>
                </div>

                <div class="sgp-change-request-action-grid border-t border-[#E8EDF0] pt-5">
                    <div class="sgp-change-request-action-buttons">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#C9D3D9] px-5 py-3 text-sm font-semibold text-[#52616A] hover:bg-[#F5F7F9]">Salvar planejamento</button>
                        @if(! $implementation || $implementation->status === \App\Enums\ChangeRequestImplementationStatus::Planning)
                            @can('startImplementation', $changeRequest)
                                <button type="submit" formaction="{{ route('projects.change-requests.implementation.start', [$project, $changeRequest]) }}" class="sgp-button-primary w-auto px-5" onclick="return confirm('Iniciar formalmente a implementação desta mudança?')">Iniciar implementação</button>
                            @endcan
                        @elseif($implementation->status === \App\Enums\ChangeRequestImplementationStatus::InProgress)
                            @can('completeImplementation', $changeRequest)
                                <button type="submit" formaction="{{ route('projects.change-requests.implementation.complete', [$project, $changeRequest]) }}" class="sgp-button-primary w-auto px-5" onclick="return confirm('Concluir a implementação e encerrar esta solicitação?')">Concluir implementação</button>
                            @endcan
                        @endif
                    </div>
                </div>
            </form>
        @elseif($implementation)
            <div class="space-y-6 p-6">
                <div class="sgp-change-request-grid-three">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Responsável</p><p class="mt-2 font-semibold text-[#24313A]">{{ $implementation->responsible?->name ?? 'Não informado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Início</p><p class="mt-2 font-semibold text-[#24313A]">{{ $implementation->started_at?->format('d/m/Y H:i') ?? 'Não iniciado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Conclusão</p><p class="mt-2 font-semibold text-[#24313A]">{{ $implementation->completed_at?->format('d/m/Y H:i') ?? 'Não concluída' }}</p></div>
                </div>
                <div class="rounded-xl border border-[#DCE3E7] p-5"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Plano</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $implementation->plan_summary ?: 'Não informado.' }}</p></div>
                <div class="grid gap-5 xl:grid-cols-2">
                    <div class="rounded-xl border border-[#DCE3E7] p-5"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Execução</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $implementation->execution_summary ?: 'Não informada.' }}</p></div>
                    <div class="rounded-xl border border-[#DCE3E7] p-5"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Verificação</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $implementation->verification_summary ?: 'Não informada.' }}</p></div>
                </div>
                <div class="sgp-change-request-grid-three">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Tratamento contratual</p><p class="mt-2 font-semibold text-[#24313A]">{{ $implementation->contract_disposition->label() }}</p><p class="mt-1 text-sm text-[#667680]">{{ $implementation->contract?->code ?? 'Sem contrato vinculado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Aditivo</p><p class="mt-2 font-semibold text-[#24313A]">{{ $implementation->amendment_reference ?: 'Não aplicável' }}</p>@if($implementation->amendment_contract_version)<p class="mt-1 text-sm text-[#667680]">Versão contratual {{ $implementation->amendment_contract_version }}</p>@endif</div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Nova baseline</p>@if($implementation->newBaseline)<a href="{{ route('projects.baselines.show', [$project, $implementation->newBaseline]) }}" class="mt-2 inline-flex font-semibold text-[#287EA1]">v{{ $implementation->newBaseline->version }} · {{ $implementation->newBaseline->title }}</a>@else<p class="mt-2 font-semibold text-[#24313A]">Não constituída</p>@endif</div>
                </div>
                @if($implementation->events->isNotEmpty())
                    <div class="border-t border-[#E8EDF0] pt-5">
                        <h3 class="font-bold text-[#24313A]">Histórico da implementação</h3>
                        <ol class="mt-3 space-y-2">
                            @foreach($implementation->events as $event)
                                <li class="flex flex-wrap justify-between gap-3 rounded-xl border border-[#E1E7EA] px-4 py-3 text-sm">
                                    <span class="font-semibold text-[#24313A]">{{ match($event->event_type) { 'plan_created' => 'Planejamento criado', 'plan_saved' => 'Planejamento atualizado', 'implementation_started' => 'Implementação iniciada', 'implementation_completed' => 'Implementação concluída', default => $event->event_type } }}</span>
                                    <span class="text-[#667680]">{{ $event->actor->name }} · {{ $event->occurred_at->format('d/m/Y H:i') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        @else
            <p class="px-6 py-10 text-center text-sm text-[#82919A]">Aguardando a gestão do projeto registrar o planejamento da implementação.</p>
        @endif
    </section>
@endif
