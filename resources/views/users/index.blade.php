<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Usuários</h1>
            <p class="mt-1 text-sm text-[#667680]">Gerencie acessos e perfis globais do sistema</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('users.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="search" class="sgp-field-label">Pesquisar</label>
                        <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Nome ou e-mail">
                    </div>
                    <div>
                        <label for="profile" class="sgp-field-label">Perfil</label>
                        <select id="profile" name="profile" class="sgp-input">
                            <option value="">Todos</option>
                            @foreach (\App\Enums\GlobalProfile::options() as $value => $label)
                                <option value="{{ $value }}" @selected($profile === $value)>{{ $label }}</option>
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
                            <button class="rounded-lg bg-[#E6F0F3] px-4 text-sm font-semibold text-[#123B4A] transition hover:bg-[#D8E8ED]">Filtrar</button>
                        </div>
                    </div>
                </form>

                <a href="{{ route('users.create') }}" class="sgp-button-primary whitespace-nowrap lg:w-auto">Novo usuário</a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Usuário</th>
                            <th class="px-5 py-4">Perfil global</th>
                            <th class="px-5 py-4">Situação</th>
                            <th class="px-5 py-4">Cadastro</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($users as $user)
                            <tr class="text-sm text-[#24313A] hover:bg-[#FBFCFD]">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ $user->name }}</p>
                                    <p class="mt-1 text-xs text-[#667680]">{{ $user->email }}</p>
                                </td>
                                <td class="px-5 py-4">{{ $user->global_profile->label() }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-[#E4F3F0] text-[#2E8B74]' : 'bg-[#F3F5F6] text-[#667680]' }}">
                                        {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-[#667680]">{{ $user->created_at->format('d/m/Y') }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('users.edit', $user) }}" class="sgp-link">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhum usuário encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-[#DCE3E7] px-5 py-4">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
