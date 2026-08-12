<x-app-layout>
    <x-slot name="header">Conversão de iniciativa</x-slot>

    <section class="mx-auto max-w-3xl space-y-5 rounded-xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
        <div>
            <p class="text-sm text-[#667680]">{{ $initiative->code }} · {{ $initiative->origin->label() }}</p>
            <h1 class="mt-1 text-xl font-semibold text-[#24313A]">{{ $initiative->title }}</h1>
            <p class="mt-3 text-sm text-[#667680]">{{ $availability['reason'] }}</p>
        </div>

        @if ($initiative->project)
            <a class="sgp-button-primary" href="{{ route('projects.show', $initiative->project) }}">Abrir projeto já convertido</a>
        @elseif ($availability['available'])
            <form method="POST" action="{{ route('initiatives.conversion.convert', $initiative) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-[#24313A]" for="client_id">Cliente ou demandante</label>
                    <select class="sgp-input mt-1 w-full" id="client_id" name="client_id" required>
                        <option value="">Selecione</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#24313A]" for="objective">Objetivo operacional</label>
                    <textarea class="sgp-input mt-1 w-full" id="objective" name="objective" rows="4" required>{{ old('objective') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#24313A]" for="justification">Justificativa complementar</label>
                    <textarea class="sgp-input mt-1 w-full" id="justification" name="justification" rows="3">{{ old('justification') }}</textarea>
                </div>
                <button class="sgp-button-primary" type="submit">Converter em projeto</button>
            </form>
        @endif

        <a class="sgp-link" href="{{ route('initiatives.index') }}">Voltar às iniciativas</a>
    </section>
</x-app-layout>
