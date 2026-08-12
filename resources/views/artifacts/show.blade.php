<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">{{ $artifact->code }} — {{ $artifact->title }}</h2></x-slot>
    <div class="py-8 max-w-5xl mx-auto space-y-6">
        @if (session('success')) <div class="rounded bg-green-100 p-3 text-green-800">{{ session('success') }}</div> @endif
        <p class="text-sm text-gray-600">Publicação DOCX/PDF ainda não está disponível para este artefato.</p>
        <div class="bg-white shadow rounded p-6"><p>{{ $artifact->description }}</p><p class="mt-2 text-gray-500">Tipo: {{ $artifact->type->label() }} · Revisão atual: {{ $artifact->current_revision_sequence }}</p></div>
        <div class="bg-white shadow rounded p-6"><h3 class="font-semibold mb-3">Histórico imutável</h3>@foreach ($artifact->revisions as $revision)
            <div class="border-t py-3"><strong>Revisão {{ $revision->sequence }}</strong> · {{ $revision->recorded_at }}<br><span class="text-sm text-gray-600">{{ $revision->change_reason }} · checksum {{ $revision->checksum }}</span><pre class="mt-2 text-xs overflow-auto">{{ json_encode($revision->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
        @endforeach</div>
        @if (! $artifact->archived_at)
        <div class="bg-white shadow rounded p-6"><h3 class="font-semibold mb-3">Nova revisão</h3><form method="post" action="{{ route('artifacts.revisions.store', $artifact) }}" class="space-y-3">@csrf
            <textarea name="content" required class="w-full border rounded h-28">{{ old('content', '{}') }}</textarea><textarea name="metadata" class="w-full border rounded h-20">{{ old('metadata') }}</textarea><input type="number" name="schema_version" value="1" min="1" max="65535" required class="border rounded"><input name="change_reason" required maxlength="10000" placeholder="Motivo da nova revisão" class="w-full border rounded">@error('content')<p class="text-red-600">{{ $message }}</p>@enderror<button class="px-4 py-2 bg-indigo-600 text-white rounded">Registrar revisão</button></form></div>
        <div class="bg-white shadow rounded p-6"><form method="post" action="{{ route('artifacts.archive', $artifact) }}">@csrf @method('patch')<input name="archive_reason" required maxlength="10000" placeholder="Motivo do arquivamento" class="border rounded p-2"><button class="ml-2 px-4 py-2 bg-gray-700 text-white rounded">Arquivar</button></form></div>
        @endif
    </div>
</x-app-layout>
