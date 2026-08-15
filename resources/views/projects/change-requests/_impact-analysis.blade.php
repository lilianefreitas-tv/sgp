@php
    $currentAnalysis = $changeRequest->impactAnalyses->sortByDesc('round')->first();
    $impactFields = [
        'scope_impact' => ['Visão e escopo', 'Alterações de objetivo, limite, entrega ou sucesso.'],
        'requirements_impact' => ['Requisitos e regras', 'RF, RNF, regras, atores, permissões e fluxos.'],
        'technical_impact' => ['Técnico e arquitetura', 'Componentes, integrações, dependências e decisões técnicas.'],
        'data_impact' => ['Dados e migração', 'Estruturas, integridade, conversão, retenção ou saneamento.'],
        'security_impact' => ['Segurança e privacidade', 'Acesso, isolamento, auditoria, exposição e dados pessoais.'],
        'schedule_impact' => ['Prazo e cronograma', 'Marcos, dependências, caminho crítico e datas.'],
        'resources_impact' => ['Recursos e equipe', 'Papéis, capacidade, especialidades e disponibilidade.'],
        'cost_impact' => ['Custo e preço', 'Custos internos, aquisição, cobrança ou bonificação.'],
        'contract_impact' => ['Contrato', 'Escopo contratado, obrigação, aceite, aditivo ou comunicação.'],
        'quality_impact' => ['Qualidade', 'Critérios, desempenho, confiabilidade e dívida técnica.'],
        'testing_impact' => ['Testes', 'Casos novos, regressão, ambiente e evidências necessárias.'],
        'operations_impact' => ['Operação', 'Implantação, suporte, observabilidade, backup e retorno.'],
        'documentation_impact' => ['Documentação', 'Artefatos, matriz, manuais e baseline afetada.'],
        'risks_and_mitigations' => ['Riscos e mitigações', 'Riscos introduzidos, probabilidade, efeito e resposta.'],
    ];
@endphp

@if($currentAnalysis || $changeRequest->state === \App\Enums\ChangeRequestState::UnderAnalysis)
    <section id="impact-analysis" class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#DCE3E7] px-6 py-5">
            <div>
                <h2 class="font-bold text-[#24313A]">Análise de impacto</h2>
                <p class="mt-1 text-sm text-[#667680]">Avaliação multidimensional que fundamenta a decisão da solicitação.</p>
            </div>
            @if($currentAnalysis)
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $currentAnalysis->status === \App\Enums\ChangeRequestAnalysisStatus::Completed ? 'bg-[#EDF8F5] text-[#256C5C]' : 'bg-[#FFF4D9] text-[#8A6400]' }}">
                    Rodada {{ $currentAnalysis->round }} · {{ $currentAnalysis->status->label() }}
                </span>
            @endif
        </div>

        @if($currentAnalysis?->status === \App\Enums\ChangeRequestAnalysisStatus::Draft && auth()->user()->can('analyzeImpact', $changeRequest))
            <form method="POST" action="{{ route('projects.change-requests.impact-analysis.update', [$project, $changeRequest]) }}" class="space-y-7 p-6">
                @csrf
                <div class="rounded-xl border border-[#C9DCE4] bg-[#F5F9FB] p-4 text-sm text-[#52616A]">
                    Salve o rascunho quantas vezes precisar. Para concluir, todos os impactos textuais devem ser preenchidos. Quando não houver impacto, registre explicitamente “Não aplicável” e a justificativa.
                    <p class="mt-2 font-semibold"><span style="color: #b42318;">*</span> Obrigatório para concluir a análise.</p>
                </div>

                <div class="sgp-change-request-grid-three">
                    <div>
                        <label for="classification" class="sgp-field-label">Classificação <span aria-hidden="true" style="color: #b42318;">*</span><span class="sr-only"> obrigatório para concluir</span></label>
                        <select id="classification" name="classification" class="sgp-input">
                            <option value="">Selecione</option>
                            @foreach($analysisClassifications as $value => $label)<option value="{{ $value }}" @selected(old('classification', $currentAnalysis->classification?->value) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="risk_level" class="sgp-field-label">Nível de risco <span aria-hidden="true" style="color: #b42318;">*</span><span class="sr-only"> obrigatório para concluir</span></label>
                        <select id="risk_level" name="risk_level" class="sgp-input">
                            <option value="">Selecione</option>
                            @foreach($analysisRiskLevels as $value => $label)<option value="{{ $value }}" @selected(old('risk_level', $currentAnalysis->risk_level?->value) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="recommendation" class="sgp-field-label">Recomendação técnica <span aria-hidden="true" style="color: #b42318;">*</span><span class="sr-only"> obrigatório para concluir</span></label>
                        <select id="recommendation" name="recommendation" class="sgp-input">
                            <option value="">Selecione</option>
                            @foreach($analysisRecommendations as $value => $label)<option value="{{ $value }}" @selected(old('recommendation', $currentAnalysis->recommendation?->value) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="executive_summary" class="sgp-field-label">Síntese executiva <span aria-hidden="true" style="color: #b42318;">*</span><span class="sr-only"> obrigatória para concluir</span></label>
                    <textarea id="executive_summary" name="executive_summary" rows="4" maxlength="10000" class="sgp-input" placeholder="Consolide a viabilidade, os principais efeitos e a orientação técnica.">{{ old('executive_summary', $currentAnalysis->executive_summary) }}</textarea>
                </div>

                <div class="sgp-change-request-grid-three">
                    @foreach($impactFields as $field => [$label, $help])
                        <div>
                            <label for="{{ $field }}" class="sgp-field-label">{{ $label }} <span aria-hidden="true" style="color: #b42318;">*</span><span class="sr-only"> obrigatório para concluir</span></label>
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="10000" class="sgp-input">{{ old($field, $currentAnalysis->{$field}) }}</textarea>
                            <p class="sgp-field-help">{{ $help }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="sgp-change-request-grid-three">
                    <div><label for="estimated_effort_hours" class="sgp-field-label">Esforço estimado, horas</label><input id="estimated_effort_hours" name="estimated_effort_hours" type="number" min="0" step="0.25" value="{{ old('estimated_effort_hours', $currentAnalysis->estimated_effort_hours) }}" class="sgp-input"></div>
                    <div><label for="estimated_schedule_days" class="sgp-field-label">Prazo estimado, dias</label><input id="estimated_schedule_days" name="estimated_schedule_days" type="number" min="0" step="1" value="{{ old('estimated_schedule_days', $currentAnalysis->estimated_schedule_days) }}" class="sgp-input"></div>
                    <div><label for="estimated_cost_amount" class="sgp-field-label">Custo estimado</label><input id="estimated_cost_amount" name="estimated_cost_amount" type="number" min="0" step="0.01" value="{{ old('estimated_cost_amount', $currentAnalysis->estimated_cost_amount) }}" class="sgp-input"></div>
                </div>

                <div class="sgp-change-request-action-grid border-t border-[#E8EDF0] pt-5">
                    <div class="sgp-change-request-action-buttons">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#C9D3D9] px-5 py-3 text-sm font-semibold text-[#52616A] hover:bg-[#F5F7F9]">Salvar rascunho</button>
                        <button type="submit" formaction="{{ route('projects.change-requests.impact-analysis.complete', [$project, $changeRequest]) }}" class="sgp-button-primary w-auto px-5" onclick="return confirm('Concluir e congelar esta rodada de análise?')">Concluir análise</button>
                    </div>
                </div>
            </form>
        @elseif($currentAnalysis)
            <div class="space-y-6 p-6">
                <div class="sgp-change-request-grid-three">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Analista</p><p class="mt-2 font-semibold text-[#24313A]">{{ $currentAnalysis->analyst->name }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Classificação</p><p class="mt-2 font-semibold text-[#24313A]">{{ $currentAnalysis->classification?->label() ?? 'Não informada' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Risco</p><p class="mt-2 font-semibold text-[#24313A]">{{ $currentAnalysis->risk_level?->label() ?? 'Não informado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Recomendação</p><p class="mt-2 font-semibold text-[#24313A]">{{ $currentAnalysis->recommendation?->label() ?? 'Não informada' }}</p></div>
                </div>
                <div class="rounded-xl border border-[#C9DCE4] bg-[#F5F9FB] p-5"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Síntese executiva</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $currentAnalysis->executive_summary ?: 'Não informada.' }}</p></div>
                <div class="sgp-change-request-grid-three">
                    @foreach($impactFields as $field => [$label])
                        <article class="rounded-xl border border-[#E1E7EA] p-4"><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">{{ $label }}</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24313A]">{{ $currentAnalysis->{$field} ?: 'Não informado.' }}</p></article>
                    @endforeach
                </div>
                <div class="sgp-change-request-grid-three">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Esforço</p><p class="mt-2 font-semibold">{{ $currentAnalysis->estimated_effort_hours !== null ? number_format((float) $currentAnalysis->estimated_effort_hours, 2, ',', '.').' h' : 'Não estimado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Prazo</p><p class="mt-2 font-semibold">{{ $currentAnalysis->estimated_schedule_days !== null ? $currentAnalysis->estimated_schedule_days.' dias' : 'Não estimado' }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-[#667680]">Custo</p><p class="mt-2 font-semibold">{{ $currentAnalysis->estimated_cost_amount !== null ? 'R$ '.number_format((float) $currentAnalysis->estimated_cost_amount, 2, ',', '.') : 'Não estimado' }}</p></div>
                </div>
                @if($currentAnalysis->completed_at)
                    <p class="border-t border-[#E8EDF0] pt-4 text-xs text-[#667680]">Concluída por {{ $currentAnalysis->completedBy->name }} em {{ $currentAnalysis->completed_at->format('d/m/Y H:i') }}. Esta rodada está congelada.</p>
                @endif
            </div>
        @else
            <p class="px-6 py-10 text-center text-sm text-[#82919A]">Inicie a análise para abrir a primeira rodada de avaliação de impacto.</p>
        @endif

        @if($changeRequest->impactAnalyses->count() > 1)
            <div class="border-t border-[#DCE3E7] px-6 py-5">
                <h3 class="font-bold text-[#24313A]">Rodadas anteriores</h3>
                <div class="mt-3 space-y-2">
                    @foreach($changeRequest->impactAnalyses->sortByDesc('round')->skip(1) as $analysis)
                        <details class="rounded-xl border border-[#E1E7EA] p-4">
                            <summary class="cursor-pointer font-semibold text-[#1D5D73]">Rodada {{ $analysis->round }} · {{ $analysis->status->label() }} · {{ $analysis->recommendation?->label() ?? 'Sem recomendação' }}</summary>
                            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#52616A]">{{ $analysis->executive_summary ?: 'Sem síntese executiva.' }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif
