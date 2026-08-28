<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-bold text-[#24313A]">Editar {{ $initiative->code }}</h1><p class="mt-1 text-sm text-[#667680]">Correção controlada da iniciativa</p></div></x-slot>
    <div class="mx-auto max-w-6xl space-y-5">
        <section class="sgp-page-intro"><p class="sgp-page-kicker">Ciclo de vida</p><h2 class="mt-2 text-2xl font-bold">Atualize sem apagar o histórico</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">Mudanças nas dimensões geram uma nova versão de configuração. Título e contexto ficam registrados na auditoria.</p></section>
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('initiative') ?: 'Revise os campos destacados.' }}</div>@endif
        <form method="post" action="{{ route('initiatives.update', $initiative) }}" class="sgp-card p-5 sm:p-7">@csrf @method('put')
            <input type="hidden" name="lock_version" value="{{ $initiative->lock_version }}">
            <section class="sgp-form-section"><h2 class="sgp-section-heading">Identificação</h2><div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div><label class="sgp-field-label" for="title">Título *</label><input class="sgp-input" id="title" name="title" value="{{ old('title', $initiative->title) }}" maxlength="200" required><x-input-error :messages="$errors->get('title')" class="mt-2" /></div>
                <div><label class="sgp-field-label" for="origin">Origem *</label><select class="sgp-input" id="origin" name="origin" required>@foreach ($origins as $value)<option value="{{ $value->value }}" @selected(old('origin', $initiative->origin->value) === $value->value)>{{ $value->label() }}</option>@endforeach</select><p class="sgp-field-help">A origem deixa de ser alterável após o início da jornada comercial.</p></div>
                <div class="lg:col-span-2"><label class="sgp-field-label" for="context">Contexto</label><textarea class="sgp-input min-h-32" id="context" name="context" maxlength="10000">{{ old('context', $initiative->context) }}</textarea></div>
            </div></section>
            <section class="sgp-form-section mt-7"><h2 class="sgp-section-heading">Configuração adaptativa</h2><p class="sgp-section-description">Qualquer alteração abaixo constitui uma nova versão prospectiva.</p><div class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach (['execution_nature' => ['Natureza da execução', $executionNatures], 'financial_management_mode' => ['Gestão financeira', $financialModes], 'management_level' => ['Nível de gestão', $managementLevels], 'methodology' => ['Metodologia', $methodologies]] as $field => [$label, $values])
                    <div><label class="sgp-field-label" for="{{ $field }}">{{ $label }} *</label><select class="sgp-input" id="{{ $field }}" name="{{ $field }}" required>@foreach ($values as $value)<option value="{{ $value->value }}" @selected(old($field, $initiative->{$field}->value) === $value->value)>{{ $value->label() }}</option>@endforeach</select></div>
                @endforeach
            </div></section>
            <section class="sgp-form-section mt-7"><h2 class="sgp-section-heading">Rastreabilidade</h2><div class="mt-5"><label class="sgp-field-label" for="justification">Justificativa da alteração *</label><textarea class="sgp-input min-h-28" id="justification" name="justification" maxlength="10000" required>{{ old('justification') }}</textarea><x-input-error :messages="$errors->get('justification')" class="mt-2" /></div></section>
            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-[#E8EDF0] pt-5 sm:flex-row sm:justify-end"><a class="sgp-button-secondary" href="{{ route('initiatives.show', $initiative) }}">Cancelar</a><button class="sgp-button-primary sm:w-auto" type="submit">Salvar alteração</button></div>
        </form>
    </div>
</x-app-layout>
