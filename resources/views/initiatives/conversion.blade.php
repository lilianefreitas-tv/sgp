<x-app-layout>
    <x-slot name="header"><div><h1 class="text-xl font-bold text-[#24313A]">Iniciar projeto</h1><p class="mt-1 text-sm text-[#667680]">Converta a iniciativa preservando origem e configuração</p></div></x-slot>
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="sgp-page-intro"><p class="sgp-page-kicker">{{ $initiative->code }} · {{ $initiative->origin->label() }}</p><h2 class="mt-2 text-2xl font-bold">{{ $initiative->title }}</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">{{ $initiative->context ?: 'Sem contexto complementar registrado.' }}</p></section>
        <section class="sgp-card p-5 sm:p-7">
            <div class="flex flex-col gap-4 border-b border-[#E8EDF0] pb-5 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="sgp-section-heading">Disponibilidade da conversão</h2><p class="sgp-section-description">{{ $availability['reason'] }}</p></div><span class="sgp-badge {{ $initiative->project || $availability['available'] ? 'sgp-badge-success' : 'sgp-badge-warning' }}">{{ $initiative->project ? 'Projeto já iniciado' : ($availability['available'] ? 'Conversão disponível' : 'Conversão indisponível') }}</span></div>
            @if ($initiative->project)
                <div class="mt-6 rounded-xl bg-[#EDF8F5] p-5"><h3 class="font-bold text-[#256C5C]">Esta iniciativa já foi convertida</h3><p class="mt-1 text-sm text-[#53636C]">Abra o projeto vinculado para continuar o planejamento e a execução.</p><a class="sgp-button-primary mt-4 sm:w-auto" href="{{ route('projects.show', $initiative->project) }}">Abrir projeto</a></div>
            @elseif ($availability['available'])
                <form method="POST" action="{{ route('initiatives.conversion.convert', $initiative) }}" class="mt-6 space-y-6">@csrf
                    <div class="grid gap-5 sm:grid-cols-2"><div><label class="sgp-field-label" for="client_id">Cliente ou demandante <span class="text-[#C44B4B]">*</span></label><select class="sgp-input" id="client_id" name="client_id" required><option value="">Selecione</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('client_id')" class="mt-2" /></div><div class="rounded-xl bg-[#F4F8F9] p-4 text-xs leading-5 text-[#53636C]"><strong class="block text-[#24313A]">Herança preservada</strong>O projeto receberá título, contexto e a versão exata das dimensões vigentes nesta iniciativa.</div></div>
                    <div><label class="sgp-field-label" for="objective">Objetivo operacional <span class="text-[#C44B4B]">*</span></label><textarea class="sgp-input min-h-28" id="objective" name="objective" maxlength="5000" required>{{ old('objective') }}</textarea><p class="sgp-field-help">Descreva o resultado concreto esperado do projeto.</p><x-input-error :messages="$errors->get('objective')" class="mt-2" /></div>
                    <div><label class="sgp-field-label" for="justification">Justificativa complementar</label><textarea class="sgp-input min-h-24" id="justification" name="justification" maxlength="5000">{{ old('justification') }}</textarea><x-input-error :messages="$errors->get('justification')" class="mt-2" /></div>
                    <div class="flex flex-col-reverse gap-3 border-t border-[#E8EDF0] pt-5 sm:flex-row sm:justify-end"><a class="sgp-button-secondary" href="{{ route('initiatives.index') }}">Cancelar</a><button class="sgp-button-primary sm:w-auto" type="submit">Converter em projeto</button></div>
                </form>
            @else
                <div class="mt-6 rounded-xl border border-[#F1DBB5] bg-[#FFF9ED] p-5 text-sm text-[#795719]">Conclua a condição indicada antes de iniciar o projeto.</div>
            @endif
        </section>
    </div>
</x-app-layout>
