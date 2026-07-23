@php($editing = isset($requirement))

@if ($errors->any())
    <div class="mb-5 rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">
        <p class="font-semibold">Revise os campos indicados:</p>
        @foreach ($errors->all() as $error)<p class="mt-1">{{ $error }}</p>@endforeach
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <div class="lg:col-span-2">
        <label for="title" class="sgp-field-label">Título <span class="text-[#C44B4B]">*</span></label>
        <input id="title" name="title" value="{{ old('title', $requirement->title ?? '') }}" class="sgp-input" maxlength="200" required>
        @error('title')<p class="mt-1 text-xs text-[#C44B4B]">{{ $message }}</p>@enderror
    </div>

    <div class="lg:col-span-2">
        <label for="description" class="sgp-field-label">Descrição detalhada</label>
        <textarea id="description" name="description" rows="5" class="sgp-input">{{ old('description', $requirement->description ?? '') }}</textarea>
        @error('description')<p class="mt-1 text-xs text-[#C44B4B]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="sgp-field-label">Tipo <span class="text-[#C44B4B]">*</span></label>
        <select id="type" name="type" class="sgp-input" required>
            @foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('type', isset($requirement) ? $requirement->type->value : 'functional') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="priority" class="sgp-field-label">Prioridade <span class="text-[#C44B4B]">*</span></label>
        <select id="priority" name="priority" class="sgp-input" required>
            @foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', isset($requirement) ? $requirement->priority->value : 'medium') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="status" class="sgp-field-label">Status <span class="text-[#C44B4B]">*</span></label>
        <select id="status" name="status" class="sgp-input" required>
            @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', isset($requirement) ? $requirement->status->value : 'proposed') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="responsible_id" class="sgp-field-label">Responsável</label>
        <select id="responsible_id" name="responsible_id" class="sgp-input">
            <option value="">Não definido</option>
            @foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) old('responsible_id', $requirement->responsible_id ?? '') === (string) $member->id)>{{ $member->name }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-[#667680]">Somente participantes ativos deste projeto.</p>
    </div>

    <div class="lg:col-span-2">
        <label for="acceptance_criteria" class="sgp-field-label">Critérios de aceite</label>
        <textarea id="acceptance_criteria" name="acceptance_criteria" rows="4" class="sgp-input" placeholder="Descreva as condições objetivas para considerar o requisito atendido.">{{ old('acceptance_criteria', $requirement->acceptance_criteria ?? '') }}</textarea>
    </div>

    <div>
        <label for="source" class="sgp-field-label">Origem da demanda</label>
        <input id="source" name="source" value="{{ old('source', $requirement->source ?? '') }}" class="sgp-input" maxlength="150" placeholder="Ex.: reunião com o cliente">
    </div>

    <div>
        <label for="is_active" class="sgp-field-label">Situação <span class="text-[#C44B4B]">*</span></label>
        <select id="is_active" name="is_active" class="sgp-input" required>
            <option value="1" @selected((string) old('is_active', isset($requirement) ? (int) $requirement->is_active : 1) === '1')>Ativo</option>
            <option value="0" @selected((string) old('is_active', isset($requirement) ? (int) $requirement->is_active : 1) === '0')>Inativo</option>
        </select>
    </div>

    @if ($editing)
        <div class="lg:col-span-2">
            <label for="change_reason" class="sgp-field-label">Motivo da alteração</label>
            <textarea id="change_reason" name="change_reason" rows="3" class="sgp-input" maxlength="1000" placeholder="Opcional. Ajuda a compreender a evolução do requisito.">{{ old('change_reason') }}</textarea>
            <p class="mt-1 text-xs text-[#667680]">Ao salvar uma mudança, a versão anterior será preservada automaticamente.</p>
        </div>
    @endif
</div>

<div class="mt-6 flex flex-wrap gap-3 border-t border-[#E8EDF0] pt-5">
    <button class="sgp-button-primary w-auto px-6">{{ $editing ? 'Salvar alterações' : 'Cadastrar requisito' }}</button>
    <a href="{{ $editing ? route('projects.requirements.show', [$project, $requirement]) : route('projects.requirements.index', $project) }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] px-5 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Cancelar</a>
</div>
