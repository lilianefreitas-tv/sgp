<x-app-layout>
    <style>
        @media (min-width: 1280px) {
            .sgp-change-request-filters {
                grid-template-columns: minmax(260px, 1.5fr) minmax(150px, 0.8fr) minmax(140px, 0.7fr) auto auto auto !important;
            }
        }
    </style>

    <x-slot name="header">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</p>
            <h1 class="truncate text-xl font-bold text-[#24313A]">Solicitações de mudança de {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-[#667680]">Registre, acompanhe e preserve a rastreabilidade das mudanças do projeto.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @include('requirements._project-nav')

        <form method="GET" class="sgp-change-request-filters grid gap-3 rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:grid-cols-2 xl:items-end">
            <div class="sm:col-span-2 xl:col-span-1">
                <label for="search" class="sgp-field-label">Pesquisar</label>
                <input id="search" name="search" value="{{ $search }}" class="sgp-input" placeholder="Código ou título">
            </div>
            <div>
                <label for="state" class="sgp-field-label">Estado</label>
                <select id="state" name="state" class="sgp-input">
                    <option value="">Todos</option>
                    @foreach ($states as $value => $label)<option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="urgency" class="sgp-field-label">Urgência</label>
                <select id="urgency" name="urgency" class="sgp-input">
                    <option value="">Todas</option>
                    @foreach ($urgencies as $value => $label)<option value="{{ $value }}" @selected($urgency === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg bg-[#E6F0F3] px-4 py-3 text-sm font-semibold text-[#123B4A] transition hover:bg-[#D8E8ED]">Filtrar</button>
            <a href="{{ route('projects.change-requests.index', $project) }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-[#DCE3E7] px-4 py-3 text-sm font-semibold text-[#667680] transition hover:bg-[#F5F7F9]">Limpar filtros</a>
            @if ($canCreate)
                <a href="{{ route('projects.change-requests.create', $project) }}" class="sgp-button-primary w-auto whitespace-nowrap px-4">Nova solicitação</a>
            @endif
        </form>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#DCE3E7] px-6 py-5">
                <div>
                    <h2 class="font-bold text-[#24313A]">Solicitações cadastradas</h2>
                    <p class="mt-1 text-sm text-[#667680]">{{ $changeRequests->total() }} {{ $changeRequests->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-6 py-4">Solicitação</th>
                            <th class="px-6 py-4">Origem</th>
                            <th class="px-6 py-4">Urgência</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4">Responsável</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($changeRequests as $item)
                            @php
                                $stateClass = match ($item->state) {
                                    \App\Enums\ChangeRequestState::Draft => 'bg-[#F1F4F6] text-[#52616A]',
                                    \App\Enums\ChangeRequestState::Submitted => 'bg-[#E8F3F6] text-[#1D5D73]',
                                    \App\Enums\ChangeRequestState::UnderAnalysis => 'bg-[#FFF4D9] text-[#8A6400]',
                                    \App\Enums\ChangeRequestState::Returned => 'bg-[#FFF0E8] text-[#A65320]',
                                    \App\Enums\ChangeRequestState::Approved => 'bg-[#EDF8F5] text-[#256C5C]',
                                    \App\Enums\ChangeRequestState::Rejected, \App\Enums\ChangeRequestState::Cancelled => 'bg-[#FFF1F1] text-[#A53E3E]',
                                    \App\Enums\ChangeRequestState::Implemented => 'bg-[#EEE9F6] text-[#594173]',
                                };
                            @endphp
                            <tr class="text-sm text-[#24313A] hover:bg-[#FAFCFC]">
                                <td class="max-w-md px-6 py-4">
                                    <a href="{{ route('projects.change-requests.show', [$project, $item]) }}" class="font-semibold text-[#1D5D73] hover:underline">{{ $item->code }} · {{ $item->title }}</a>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-[#667680]">{{ $item->description ?: 'Sem descrição detalhada.' }}</p>
                                </td>
                                <td class="px-6 py-4">{{ $item->origin->label() }}</td>
                                <td class="px-6 py-4">{{ $item->urgency?->label() ?? 'Não definida' }}</td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $stateClass }}">{{ $item->state->label() }}</span></td>
                                <td class="px-6 py-4">
                                    <p>{{ $item->analyst?->name ?? 'Não definido' }}</p>
                                    <p class="mt-1 text-xs text-[#82919A]">Solicitante: {{ $item->requester->name }}</p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('projects.change-requests.show', [$project, $item]) }}" class="font-semibold text-[#287EA1] hover:underline">Visualizar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F0F3] text-[#123B4A]">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </div>
                                    <p class="mt-4 font-semibold text-[#24313A]">Nenhuma solicitação encontrada</p>
                                    <p class="mt-1 text-sm text-[#667680]">Registre a primeira solicitação ou ajuste os filtros da pesquisa.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($changeRequests->hasPages())
                <div class="border-t border-[#E8EDF0] px-6 py-4">{{ $changeRequests->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
