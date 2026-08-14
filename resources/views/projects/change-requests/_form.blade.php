@php
    $selectedAffected = collect(old('affected', $changeRequest->exists
        ? $changeRequest->affectedItems->groupBy('item_type')->map->pluck('source_id')->map->all()->all()
        : []));
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h2 class="font-bold text-[#24313A]">Identificação da mudança</h2>
            <p class="mt-1 text-sm text-[#667680]">O rascunho começa simples. A análise completa será feita em uma etapa posterior.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label for="title" class="sgp-field-label">Título</label>
                <input id="title" name="title" value="{{ old('title', $changeRequest->title) }}" maxlength="200" class="sgp-input" required autofocus>
                @error('title')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="origin" class="sgp-field-label">Origem</label>
                <select id="origin" name="origin" class="sgp-input" required>
                    <option value="">Selecione</option>
                    @foreach($origins as $value => $label)
                        <option value="{{ $value }}" @selected(old('origin', $changeRequest->origin?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('origin')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="urgency" class="sgp-field-label">Urgência <span class="font-normal text-[#82919A]">(opcional no rascunho)</span></label>
                <select id="urgency" name="urgency" class="sgp-input">
                    <option value="">Definir depois</option>
                    @foreach($urgencies as $value => $label)
                        <option value="{{ $value }}" @selected(old('urgency', $changeRequest->urgency?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('urgency')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="sgp-field-label">Descrição <span class="font-normal text-[#82919A]">(obrigatória para submeter)</span></label>
                <textarea id="description" name="description" rows="5" maxlength="10000" class="sgp-input">{{ old('description', $changeRequest->description) }}</textarea>
                @error('description')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="justification" class="sgp-field-label">Justificativa <span class="font-normal text-[#82919A]">(obrigatória para submeter)</span></label>
                <textarea id="justification" name="justification" rows="4" maxlength="10000" class="sgp-input">{{ old('justification', $changeRequest->justification) }}</textarea>
                @error('justification')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <h2 class="font-bold text-[#24313A]">Referências e responsáveis</h2>
        <p class="mt-1 text-sm text-[#667680]">Baseline, itens afetados e responsável pela análise podem ser informados depois.</p>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <div>
                <label for="baseline_id" class="sgp-field-label">Baseline de referência</label>
                <select id="baseline_id" name="baseline_id" class="sgp-input">
                    <option value="">Não definida</option>
                    @foreach($project->baselines as $baseline)
                        <option value="{{ $baseline->id }}" @selected((string) old('baseline_id', $changeRequest->baseline_id) === (string) $baseline->id)>v{{ $baseline->version }} · {{ $baseline->title }}</option>
                    @endforeach
                </select>
                @error('baseline_id')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="requester_id" class="sgp-field-label">Solicitante</label>
                @if($canManageProject)
                    <select id="requester_id" name="requester_id" class="sgp-input">
                        @foreach($projectUsers as $userOption)
                            <option value="{{ $userOption->id }}" @selected((string) old('requester_id', $changeRequest->requester_id ?: auth()->id()) === (string) $userOption->id)>{{ $userOption->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="requester_id" value="{{ $changeRequest->requester_id ?: auth()->id() }}">
                    <div class="sgp-input bg-[#F5F7F9]">{{ $changeRequest->requester?->name ?? auth()->user()->name }}</div>
                @endif
                @error('requester_id')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="analyst_id" class="sgp-field-label">Responsável pela análise</label>
                @if($canManageProject)
                    <select id="analyst_id" name="analyst_id" class="sgp-input">
                        <option value="">Atribuir depois</option>
                        @foreach($projectUsers as $userOption)
                            <option value="{{ $userOption->id }}" @selected((string) old('analyst_id', $changeRequest->analyst_id) === (string) $userOption->id)>{{ $userOption->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="analyst_id" value="{{ $changeRequest->analyst_id }}">
                    <div class="sgp-input bg-[#F5F7F9]">{{ $changeRequest->analyst?->name ?? 'Atribuir depois' }}</div>
                @endif
                @error('analyst_id')<p class="mt-1 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <h2 class="font-bold text-[#24313A]">Itens potencialmente afetados</h2>
        <p class="mt-1 text-sm text-[#667680]">Esta seleção é preliminar e não substitui a análise de impacto do P07.2.</p>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            @php
                $groups = [
                    'requirement' => ['label' => 'Requisitos', 'items' => $project->requirements],
                    'task' => ['label' => 'Tarefas', 'items' => $project->tasks],
                    'artifact' => ['label' => 'Registros documentais', 'items' => $project->artifacts],
                    'contract' => ['label' => 'Contratos', 'items' => $project->contracts],
                    'document' => ['label' => 'Documentos gerados', 'items' => $project->documents],
                ];
            @endphp
            @foreach($groups as $type => $group)
                <fieldset class="rounded-xl border border-[#E3E9EC] bg-[#F8FAFB] p-4">
                    <legend class="px-2 text-sm font-bold text-[#24313A]">{{ $group['label'] }}</legend>
                    <div class="mt-2 max-h-48 space-y-2 overflow-y-auto pr-1">
                        @forelse($group['items'] as $item)
                            @php($itemCode = $item->code ?? ($type === 'document' ? 'DOC-'.$item->id.'-v'.$item->version : null))
                            <label class="flex items-start gap-3 rounded-lg border border-[#E3E9EC] bg-white px-3 py-2 text-sm text-[#24313A]">
                                <input type="checkbox" name="affected[{{ $type }}][]" value="{{ $item->id }}" class="mt-0.5 rounded border-[#B8C5CB] text-[#123B4A] focus:ring-[#287EA1]" @checked(in_array($item->id, $selectedAffected->get($type, [])))>
                                <span><span class="font-semibold">{{ $itemCode }}</span>{{ $itemCode ? ' · ' : '' }}{{ $item->title }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-[#82919A]">Nenhum item disponível.</p>
                        @endforelse
                    </div>
                    @error('affected.'.$type)<p class="mt-2 text-sm text-[#A53E3E]">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ $changeRequest->exists ? route('projects.change-requests.show', [$project, $changeRequest]) : route('projects.change-requests.index', $project) }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] bg-white px-4 py-2.5 text-sm font-semibold text-[#24313A] hover:bg-[#F5F7F9]">Cancelar</a>
        <button class="sgp-button-primary w-auto px-5 py-2.5">{{ $changeRequest->exists ? 'Salvar alterações' : 'Salvar rascunho' }}</button>
    </div>
</div>
