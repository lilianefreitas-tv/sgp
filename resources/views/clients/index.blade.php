<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Clientes e unidades</h1>
            <p class="mt-1 text-sm text-[#667680]">Gerencie clientes, órgãos, setores e unidades demandantes</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <form method="GET" action="{{ route('clients.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="search" class="sgp-field-label">Pesquisar</label>
                        <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Nome, contato ou documento">
                    </div>
                    <div>
                        <label for="type" class="sgp-field-label">Tipo</label>
                        <select id="type" name="type" class="sgp-input">
                            <option value="">Todos</option>
                            @foreach (\App\Enums\ClientType::options() as $value => $label)
                                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="sgp-field-label">Situação</label>
                        <div class="flex gap-2">
                            <select id="status" name="status" class="sgp-input">
                                <option value="">Todas</option>
                                <option value="active" @selected($status === 'active')>Ativos</option>
                                <option value="inactive" @selected($status === 'inactive')>Inativos</option>
                            </select>
                            <button class="rounded-lg bg-[#E6F0F3] px-4 text-sm font-semibold text-[#123B4A] hover:bg-[#D8E8ED]">Filtrar</button>
                        </div>
                    </div>
                </form>

                <a href="{{ route('clients.create') }}" class="sgp-button-primary whitespace-nowrap xl:w-auto">Novo cliente ou unidade</a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Nome</th>
                            <th class="px-5 py-4">Tipo</th>
                            <th class="px-5 py-4">Contato</th>
                            <th class="px-5 py-4">Projetos</th>
                            <th class="px-5 py-4">Situação</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($clients as $client)
                            <tr class="text-sm text-[#24313A] hover:bg-[#FBFCFD]">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ $client->name }}</p>
                                    @if ($client->document)<p class="mt-1 text-xs text-[#667680]">{{ $client->document }}</p>@endif
                                </td>
                                <td class="px-5 py-4">{{ $client->type->label() }}</td>
                                <td class="px-5 py-4">
                                    <p>{{ $client->contact_name ?: 'Não informado' }}</p>
                                    @if ($client->email)<p class="mt-1 text-xs text-[#667680]">{{ $client->email }}</p>@endif
                                </td>
                                <td class="px-5 py-4">{{ $client->projects_count }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $client->is_active ? 'bg-[#E4F3F0] text-[#2E8B74]' : 'bg-[#F3F5F6] text-[#667680]' }}">{{ $client->is_active ? 'Ativo' : 'Inativo' }}</span>
                                </td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('clients.edit', $client) }}" class="sgp-link">Editar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhum cliente ou unidade encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($clients->hasPages())
                <div class="border-t border-[#DCE3E7] px-5 py-4">{{ $clients->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
