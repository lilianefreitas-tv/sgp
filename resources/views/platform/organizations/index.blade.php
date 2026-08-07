<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Administração da Plataforma</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre, consulte e controle as organizações do SGP</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @if (session('info'))
            <div class="rounded-xl border border-[#C9D9E1] bg-[#F0F6F8] px-4 py-3 text-sm font-medium text-[#31596A]">{{ session('info') }}</div>
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="sgp-organization-toolbar">
                <form method="GET" action="{{ route('platform.organizations.index') }}" class="sgp-organization-filters">
                    <div class="sgp-organization-search">
                        <label for="search" class="sgp-field-label">Pesquisar organização</label>
                        <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Digite o nome ou identificador">
                    </div>

                    <div class="sgp-organization-status">
                        <label for="status" class="sgp-field-label">Situação</label>
                        <select id="status" name="status" class="sgp-input">
                            <option value="">Todas</option>
                            @foreach (\App\Enums\OrganizationStatus::cases() as $option)
                                <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button class="sgp-button-secondary sgp-filter-action">Filtrar</button>

                    @if ($search !== '' || $status !== '')
                        <a href="{{ route('platform.organizations.index') }}" class="sgp-clear-action">Limpar</a>
                    @endif
                </form>

                <a href="{{ route('platform.organizations.create') }}" class="sgp-button-primary sgp-admin-primary-action">
                    <span aria-hidden="true">+</span>
                    Nova organização
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#E8EDF0] px-5 py-4">
                <h2 class="text-base font-bold text-[#24313A]">Organizações cadastradas</h2>
                <p class="mt-1 text-sm text-[#667680]">Selecione uma organização para editar seus dados e sua situação.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Organização</th>
                            <th class="px-5 py-4">Tipo</th>
                            <th class="px-5 py-4">Administrador principal</th>
                            <th class="px-5 py-4">Usuários ativos</th>
                            <th class="px-5 py-4">Situação</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($organizations as $organization)
                            <tr class="text-sm text-[#24313A] hover:bg-[#FBFCFD]">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ $organization->name }}</p>
                                    <p class="mt-1 text-xs text-[#667680]">{{ $organization->slug }} · {{ $organization->timezone }}</p>
                                </td>
                                <td class="px-5 py-4">{{ $organization->type?->label() ?? 'Não informado' }}</td>
                                <td class="px-5 py-4">
                                    @forelse ($organization->memberships as $owner)
                                        <p>{{ $owner->user->name }}</p>
                                        <p class="text-xs text-[#667680]">{{ $owner->user->email }}</p>
                                    @empty
                                        <span class="font-semibold text-[#A55252]">Sem Administrador principal ativo</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-4">{{ $organization->active_members_count }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $organization->isActive() ? 'bg-[#E4F3F0] text-[#2E8B74]' : 'bg-[#FBECEC] text-[#A55252]' }}">
                                        {{ $organization->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('platform.organizations.edit', $organization) }}" class="sgp-link">Editar</a>

                                        @if ($organization->isActive())
                                            <form method="POST" action="{{ route('platform.organizations.access', $organization) }}">
                                                @csrf
                                                <button type="submit" class="sgp-button-primary whitespace-nowrap">
                                                    Acessar organização
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhuma organização encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($organizations->hasPages())
                <div class="border-t border-[#DCE3E7] px-5 py-4">{{ $organizations->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
