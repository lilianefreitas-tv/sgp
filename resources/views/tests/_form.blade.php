@php($editing = isset($testCase))
<div class="space-y-6">
    <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <h2 class="text-base font-bold text-[#24313A]">Identificação e finalidade</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2"><label class="sgp-field-label" for="title">Título *</label><input class="sgp-input" id="title" name="title" value="{{ old('title', $testCase->title ?? '') }}" maxlength="200" required></div>
            <div class="lg:col-span-2"><label class="sgp-field-label" for="objective">Objetivo *</label><textarea class="sgp-input min-h-24" id="objective" name="objective" required>{{ old('objective', $testCase->objective ?? '') }}</textarea></div>
            <div><label class="sgp-field-label" for="severity">Severidade *</label><select class="sgp-input" id="severity" name="severity" required>@foreach($severities as $value => $label)<option value="{{ $value }}" @selected(old('severity', isset($testCase) ? $testCase->severity->value : 'medium') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="sgp-field-label" for="status">Situação *</label><select class="sgp-input" id="status" name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', isset($testCase) ? $testCase->status->value : 'draft') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="sgp-field-label" for="assigned_tester_id">Testador designado</label><select class="sgp-input" id="assigned_tester_id" name="assigned_tester_id"><option value="">Sem designação individual</option>@foreach($testers as $tester)<option value="{{ $tester->id }}" @selected((string)old('assigned_tester_id', $testCase->assigned_tester_id ?? '') === (string)$tester->id)>{{ $tester->name }}</option>@endforeach</select><p class="mt-1 text-xs text-[#667680]">Somente participantes com o papel Testador aparecem aqui.</p></div>
            <div><label class="sgp-field-label" for="requirement_id">Requisito relacionado</label><select class="sgp-input" id="requirement_id" name="requirement_id"><option value="">Não vinculado</option>@foreach($requirements as $item)<option value="{{ $item->id }}" @selected((string)old('requirement_id', $testCase->requirement_id ?? '') === (string)$item->id)>{{ $item->code }} · {{ $item->title }}</option>@endforeach</select></div>
            <div><label class="sgp-field-label" for="change_request_id">Solicitação de mudança</label><select class="sgp-input" id="change_request_id" name="change_request_id"><option value="">Não vinculada</option>@foreach($changeRequests as $item)<option value="{{ $item->id }}" @selected((string)old('change_request_id', $testCase->change_request_id ?? '') === (string)$item->id)>{{ $item->code }} · {{ $item->title }}</option>@endforeach</select></div>
            <div><label class="sgp-field-label" for="baseline_id">Baseline de referência</label><select class="sgp-input" id="baseline_id" name="baseline_id"><option value="">Sem baseline específica</option>@foreach($baselines as $item)<option value="{{ $item->id }}" @selected((string)old('baseline_id', $testCase->baseline_id ?? '') === (string)$item->id)>v{{ $item->version }} · {{ $item->title }}</option>@endforeach</select></div>
        </div>
    </section>
    <section class="rounded-2xl border border-[#BFE2D9] bg-[#F7FBFA] p-6 shadow-sm">
        <h2 class="text-base font-bold text-[#256C5C]">Preparação e resultado esperado</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div><label class="sgp-field-label" for="preconditions">Precondições</label><textarea class="sgp-input min-h-32" id="preconditions" name="preconditions">{{ old('preconditions', $testCase->preconditions ?? '') }}</textarea></div>
            <div><label class="sgp-field-label" for="test_data">Dados de teste</label><textarea class="sgp-input min-h-32" id="test_data" name="test_data">{{ old('test_data', $testCase->test_data ?? '') }}</textarea></div>
            <div><label class="sgp-field-label" for="steps">Passos *</label><textarea class="sgp-input min-h-52" id="steps" name="steps" required placeholder="1. Acesse...&#10;2. Informe...&#10;3. Confirme...">{{ old('steps', $testCase->steps ?? '') }}</textarea></div>
            <div><label class="sgp-field-label" for="expected_result">Resultado esperado *</label><textarea class="sgp-input min-h-52" id="expected_result" name="expected_result" required>{{ old('expected_result', $testCase->expected_result ?? '') }}</textarea></div>
        </div>
    </section>
</div>
