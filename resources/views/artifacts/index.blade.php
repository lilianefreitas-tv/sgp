<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Artefatos estruturados — {{ $parent->title ?? $parent->name }}</h2></x-slot>
    <div class="py-8 max-w-5xl mx-auto space-y-6">
        @if (session('success')) <div class="rounded bg-green-100 p-3 text-green-800">{{ session('success') }}</div> @endif
        <p class="text-sm text-gray-600">Esta fundação registra conteúdo estruturado e revisões; publicação DOCX/PDF ainda não está disponível.</p>
        <div class="bg-white shadow rounded p-6">
            <h3 class="font-semibold mb-3">Novo artefato</h3>
            <form method="post" action="{{ $parentType === 'initiative' ? route('initiatives.artifacts.store', $parent) : route('projects.artifacts.store', $parent) }}" class="space-y-3">
                @csrf
                <select name="type" required class="w-full border rounded">@foreach (\App\Enums\ArtifactType::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                <input name="title" value="{{ old('title') }}" required maxlength="255" placeholder="Título" class="w-full border rounded">
                <textarea name="description" maxlength="10000" placeholder="Descrição (opcional)" class="w-full border rounded">{{ old('description') }}</textarea>
                <textarea name="content" required placeholder='Conteúdo JSON, por exemplo {"campo":"valor"}' class="w-full border rounded h-28">{{ old('content', '{}') }}</textarea>
                <textarea name="metadata" placeholder='Metadados JSON (opcional)' class="w-full border rounded h-20">{{ old('metadata') }}</textarea>
                <input type="number" name="schema_version" value="{{ old('schema_version', 1) }}" min="1" max="65535" required class="border rounded">
                <input name="change_reason" value="{{ old('change_reason', 'Registro inicial.') }}" required maxlength="10000" class="w-full border rounded">
                @error('content')<p class="text-red-600">{{ $message }}</p>@enderror
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Criar artefato</button>
            </form>
        </div>
        <div class="bg-white shadow rounded divide-y">@forelse ($artifacts as $artifact)
            <a class="block p-4 hover:bg-gray-50" href="{{ route('artifacts.show', $artifact) }}"><strong>{{ $artifact->code }}</strong> — {{ $artifact->title }} <span class="text-gray-500">rev. {{ $artifact->current_revision_sequence }}{{ $artifact->archived_at ? ' (arquivado)' : '' }}</span></a>
        @empty <p class="p-4 text-gray-500">Nenhum artefato registrado.</p>@endforelse</div>
    </div>
</x-app-layout>
