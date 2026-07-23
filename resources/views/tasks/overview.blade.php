<x-app-layout>
    <x-slot name="header">
        <div><h1 class="text-xl font-bold text-[#24313A]">Tarefas</h1><p class="mt-1 text-sm text-[#667680]">Consulte as tarefas dos projetos aos quais você possui acesso.</p></div>
    </x-slot>
    <div class="space-y-5">
        <form method="GET" action="{{ route('tasks.index') }}" class="grid gap-3 rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(210px,1.35fr)_repeat(4,minmax(110px,0.85fr))_auto_auto] xl:items-end">
            <div class="sm:col-span-2 xl:col-span-1"><label for="search" class="sgp-field-label">Pesquisar</label><input id="search" name="search" value="{{ $search }}" class="sgp-input" placeholder="Código, título ou descrição"></div>
            <div><label for="status" class="sgp-field-label">Status</label><select id="status" name="status" class="sgp-input"><option value="">Todos</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="priority" class="sgp-field-label">Prioridade</label><select id="priority" name="priority" class="sgp-input"><option value="">Todas</option>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="responsibility" class="sgp-field-label">Responsável</label><select id="responsibility" name="responsibility" class="sgp-input"><option value="">Todos</option><option value="mine" @selected($responsibility === 'mine')>Minhas tarefas</option></select></div>
            <div><label for="activity" class="sgp-field-label">Situação</label><select id="activity" name="activity" class="sgp-input"><option value="active" @selected($activity === 'active')>Ativas</option><option value="inactive" @selected($activity === 'inactive')>Inativas</option></select></div>
            <button class="inline-flex items-center justify-center whitespace-nowrap rounded-lg bg-[#E6F0F3] px-4 py-3 text-sm font-semibold text-[#123B4A] hover:bg-[#D8E8ED]">Filtrar</button>
            <a href="{{ route('tasks.index') }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-[#DCE3E7] px-4 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Limpar filtros</a>
        </form>
        @include('tasks._table', ['showProject' => true])
    </div>
</x-app-layout>
