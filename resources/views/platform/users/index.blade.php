<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Usuários da plataforma</h1>
            <p class="mt-1 text-sm text-[#667680]">Gerencie as contas globais e consulte os vínculos com organizações</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="rounded-xl border border-[#E8D5A7] bg-[#FFF9E9] px-4 py-3 text-sm font-medium text-[#7A5B18]">{{ session('warning') }}</div>
        @endif

        <section class="rounded-2xl border border-[#D7E6EA] bg-[#F2F8FA] p-5 text-sm text-[#36525E] shadow-sm">
            <p class="font-semibold text-[#24313A]">Conta global não é vínculo organizacional</p>
            <p class="mt-1 leading-6">Criar uma conta aqui permite que a pessoa acesse o SGP, mas não concede acesso a nenhuma empresa. O vínculo é definido ao criar uma organização ou pela opção <strong>Equipe da organização</strong>.</p>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('platform.users.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="search" class="sgp-field-label">Pesquisar usuário</label>
                        <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Digite o nome ou e-mail">
                    </div>
                    <div>
                        <label for="profile" class="sgp-field-label">Perfil global</label>
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

                <a href="{{ route('platform.users.create') }}" class="sgp-button-primary whitespace-nowrap lg:w-auto">
                    <span aria-hidden="true">+</span>
                    Novo usuário
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#E8EDF0] px-5 py-4">
                <h2 class="text-base font-bold text-[#24313A]">Contas cadastradas</h2>
                <p class="mt-1 text-sm text-[#667680]">Os papéis de projeto continuam sendo definidos separadamente em cada projeto.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Usuário</th>
                            <th class="px-5 py-4">Perfil global</th>
                            <th class="px-5 py-4">Organizações</th>
                            <th class="px-5 py-4">Situação</th>
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
                                    <p class="font-semibold">{{ $user->active_organizations_count }} ativa(s)</p>
                                    @if ($user->organizationMemberships->isNotEmpty())
                                        <p class="mt-1 max-w-md text-xs leading-5 text-[#667680]">
                                            {{ $user->organizationMemberships->map(fn ($membership) => $membership->organization?->name)->filter()->join(', ') }}
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-[#667680]">Sem vínculo organizacional</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-[#E4F3F0] text-[#2E8B74]' : 'bg-[#FBECEC] text-[#A55252]' }}">
                                        {{ $user->is_active ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-3">
                                        @if ($user->is_active)
                                            <form method="POST" action="{{ route('platform.users.password-reset-link', $user) }}" onsubmit="return confirm('Enviar um novo link de redefinição para o e-mail cadastrado?');">
                                                @csrf
                                                <button class="sgp-link">Reenviar link</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('platform.users.edit', $user) }}" class="sgp-link">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhuma conta encontrada.</td></tr>
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
