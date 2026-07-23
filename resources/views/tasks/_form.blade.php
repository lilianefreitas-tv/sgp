@php($editing = isset($task))

@if ($errors->any())
    <div class="mb-5 rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">
        <p class="font-semibold">Revise os campos indicados:</p>
        @foreach ($errors->all() as $error)<p class="mt-1">{{ $error }}</p>@endforeach
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <div class="lg:col-span-2">
        <label for="title" class="sgp-field-label">Título <span class="text-[#C44B4B]">*</span></label>
        <input id="title" name="title" value="{{ old('title', $task->title ?? '') }}" class="sgp-input" maxlength="200" required>
    </div>

    <div class="lg:col-span-2">
        <label for="description" class="sgp-field-label">Descrição detalhada</label>
        <textarea id="description" name="description" rows="5" class="sgp-input">{{ old('description', $task->description ?? '') }}</textarea>
    </div>

    <div>
        <label for="priority" class="sgp-field-label">Prioridade <span class="text-[#C44B4B]">*</span></label>
        <select id="priority" name="priority" class="sgp-input" required>
            @foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', isset($task) ? $task->priority->value : 'medium') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="status" class="sgp-field-label">Status <span class="text-[#C44B4B]">*</span></label>
        <select id="status" name="status" class="sgp-input" required>
            @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', isset($task) ? $task->status->value : 'backlog') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="responsible_id" class="sgp-field-label">Responsável principal</label>
        <select id="responsible_id" name="responsible_id" class="sgp-input">
            <option value="">Não definido</option>
            @foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) old('responsible_id', $task->responsible_id ?? '') === (string) $member->id)>{{ $member->name }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-[#667680]">Somente participantes ativos deste projeto.</p>
    </div>

    <div>
        <label for="requirement_id" class="sgp-field-label">Requisito vinculado</label>
        <select id="requirement_id" name="requirement_id" class="sgp-input">
            <option value="">Nenhum requisito</option>
            @foreach ($requirements as $requirement)<option value="{{ $requirement->id }}" @selected((string) old('requirement_id', $task->requirement_id ?? '') === (string) $requirement->id)>{{ $requirement->code }} · {{ $requirement->title }}</option>@endforeach
        </select>
    </div>

    <div>
        <label for="parent_task_id" class="sgp-field-label">Tarefa principal</label>
        <select id="parent_task_id" name="parent_task_id" class="sgp-input">
            <option value="">Não é subtarefa</option>
            @foreach ($parentTasks as $parentTask)<option value="{{ $parentTask->id }}" @selected((string) old('parent_task_id', $task->parent_task_id ?? '') === (string) $parentTask->id)>{{ $parentTask->code }} · {{ $parentTask->title }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-[#667680]">Nesta versão, a hierarquia permite apenas um nível.</p>
    </div>

    <div>
        <label for="estimated_duration" class="sgp-field-label">Estimativa (HH:MM)</label>
        <input id="estimated_duration" name="estimated_duration" type="text" inputmode="numeric" pattern="[0-9]{2,6}:[0-5][0-9]" maxlength="9" value="{{ old('estimated_duration', isset($task) ? $task->estimatedDuration() : '') }}" class="sgp-input" placeholder="Ex.: 08:00">
        <p class="mt-1 text-xs text-[#667680]">Informe horas e minutos. Exemplos: 00:15, 01:30 ou 08:00.</p>
    </div>

    <div>
        <label for="start_date" class="sgp-field-label">Data de início</label>
        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', isset($task) ? $task->start_date?->format('Y-m-d') : '') }}" class="sgp-input">
    </div>

    <div>
        <label for="due_date" class="sgp-field-label">Prazo previsto</label>
        <input id="due_date" name="due_date" type="date" value="{{ old('due_date', isset($task) ? $task->due_date?->format('Y-m-d') : '') }}" class="sgp-input">
    </div>

    <div>
        <label for="is_active" class="sgp-field-label">Situação <span class="text-[#C44B4B]">*</span></label>
        <select id="is_active" name="is_active" class="sgp-input" required>
            <option value="1" @selected((string) old('is_active', isset($task) ? (int) $task->is_active : 1) === '1')>Ativa</option>
            <option value="0" @selected((string) old('is_active', isset($task) ? (int) $task->is_active : 1) === '0')>Inativa</option>
        </select>
    </div>

    @if ($editing)
        <div class="lg:col-span-2">
            <label for="change_notes" class="sgp-field-label">Observação da alteração</label>
            <textarea id="change_notes" name="change_notes" rows="3" class="sgp-input" maxlength="1000" placeholder="Opcional. Registre o motivo ou contexto da mudança.">{{ old('change_notes') }}</textarea>
        </div>
    @endif
</div>

<div class="mt-6 flex flex-wrap gap-3 border-t border-[#E8EDF0] pt-5">
    <button class="sgp-button-primary w-auto px-6">{{ $editing ? 'Salvar alterações' : 'Cadastrar tarefa' }}</button>
    <a href="{{ $editing ? route('projects.tasks.show', [$project, $task]) : route('projects.tasks.index', $project) }}" class="inline-flex items-center rounded-lg border border-[#DCE3E7] px-5 py-2.5 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Cancelar</a>
</div>
