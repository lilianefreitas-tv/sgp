<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Equipe da organização</h1>
            <p class="mt-1 text-sm text-[#667680]">Cadastre pessoas e gerencie os acessos de {{ $activeOrganization->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl border border-[#E8D5A7] bg-[#FFF9E9] px-4 py-3 text-sm font-medium text-[#7A5B18]">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-[#F0CACA] bg-[#FFF4F4] px-4 py-3 text-sm text-[#914747]">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl border border-[#D7E6EA] bg-[#F2F8FA] p-5 shadow-sm">
            <div class="sgp-membership-explanation">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-[#287EA1]">Como funciona</p>
                    <h2 class="mt-1 text-base font-bold text-[#24313A]">Primeiro a pessoa entra na equipe; depois, recebe funções nos projetos</h2>
                    <p class="mt-1 text-sm leading-6 text-[#53636C]">Você pode criar uma conta nova ou associar uma conta existente a <strong>{{ $activeOrganization->name }}</strong>. Gerente, DBA, Cliente e outras funções são definidas separadamente dentro de cada projeto.</p>
                </div>

                <ol class="sgp-membership-steps" aria-label="Etapas do vínculo">
                    <li><span>1</span><p><strong>Cadastre</strong> ou localize a conta.</p></li>
                    <li><span>2</span><p><strong>Defina</strong> o nível de acesso à organização.</p></li>
                    <li><span>3</span><p><strong>Adicione</strong> a pessoa aos projetos necessários.</p></li>
                </ol>
            </div>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm" x-data="{ accountMode: @js(old('account_mode', 'new')) }">
            <div>
                <h2 class="text-base font-bold text-[#24313A]">Adicionar pessoa à equipe</h2>
                <p class="mt-1 text-sm text-[#667680]">Crie uma conta ou associe uma conta já existente, sem expor a lista global de usuários.</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <label class="inline-flex items-center gap-2 rounded-lg border border-[#CAD5DA] bg-[#F8FAFB] px-4 py-2 text-sm font-semibold text-[#36525E]"><input type="radio" name="account_mode_selector" value="new" x-model="accountMode"> Nova conta</label>
                <label class="inline-flex items-center gap-2 rounded-lg border border-[#CAD5DA] bg-[#F8FAFB] px-4 py-2 text-sm font-semibold text-[#36525E]"><input type="radio" name="account_mode_selector" value="existing" x-model="accountMode"> Conta existente</label>
            </div>

            <form method="POST" action="{{ route('organization-members.store') }}" class="mt-5 grid gap-5 lg:grid-cols-2">
                @csrf
                <input type="hidden" name="account_mode" :value="accountMode">
                <div x-show="accountMode === 'existing'" x-cloak>
                    <label for="existing_user_email" class="sgp-field-label">E-mail da conta existente *</label>
                    <input id="existing_user_email" type="email" name="existing_user_email" class="sgp-input" value="{{ old('existing_user_email') }}" placeholder="usuario@empresa.com" :required="accountMode === 'existing'">
                </div>
                <div x-show="accountMode === 'new'" x-cloak>
                    <label for="new_user_name" class="sgp-field-label">Nome *</label>
                    <input id="new_user_name" name="new_user_name" class="sgp-input" maxlength="255" value="{{ old('new_user_name') }}" :required="accountMode === 'new'">
                </div>
                <div x-show="accountMode === 'new'" x-cloak>
                    <label for="new_user_email" class="sgp-field-label">E-mail *</label>
                    <input id="new_user_email" type="email" name="new_user_email" class="sgp-input" maxlength="255" value="{{ old('new_user_email') }}" :required="accountMode === 'new'">
                </div>
                <div class="lg:col-span-2">
                    <label for="role_code" class="sgp-field-label">Nível de acesso à organização *</label>
                    <select id="role_code" name="role_code" class="sgp-input" required>
                        @foreach ($roles as $value => $label)
                            @if ($value !== \App\Enums\OrganizationRole::Owner->value || $currentRole === \App\Enums\OrganizationRole::Owner)
                                <option value="{{ $value }}" @selected(old('role_code') === $value)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-[#667680]" x-show="accountMode === 'new'">Após salvar, o SGP enviará por e-mail um link temporário para a pessoa definir a própria senha.</p>
                    <button class="sgp-button-primary lg:w-auto">Adicionar à equipe</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="sgp-member-list-header">
                <div>
                    <h2 class="text-base font-bold text-[#24313A]">Pessoas da equipe</h2>
                    <p class="mt-1 text-sm text-[#667680]">Altere o nível de acesso ou a situação de cada pessoa.</p>
                </div>

                <form method="GET" action="{{ route('organization-members.index') }}" class="sgp-member-search-form">
                    <div>
                    <label for="search" class="sgp-field-label">Pesquisar equipe</label>
                    <input id="search" name="search" class="sgp-input" value="{{ $search }}" placeholder="Nome ou e-mail">
                    </div>
                    <button class="sgp-button-secondary sgp-member-search-action">Pesquisar</button>
                    @if ($search !== '')
                        <a href="{{ route('organization-members.index') }}" class="sgp-clear-action">Limpar</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#DCE3E7]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-5 py-4">Usuário</th>
                            <th class="px-5 py-4">Nível de acesso</th>
                            <th class="px-5 py-4">Situação</th>
                            <th class="px-5 py-4">Vinculado em</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @forelse ($memberships as $membership)
                            @php($protectedOwner = $currentRole === \App\Enums\OrganizationRole::Administrator && $membership->role_code === \App\Enums\OrganizationRole::Owner)
                            <tr class="text-sm text-[#24313A]">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ $membership->user->name }}</p>
                                    <p class="mt-1 text-xs text-[#667680]">{{ $membership->user->email }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($protectedOwner)
                                        <p class="font-semibold">{{ $membership->role_code->label() }}</p>
                                    @else
                                        <form id="membership-update-{{ $membership->id }}" method="POST" action="{{ route('organization-members.update', $membership->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role_code" class="sgp-input py-2 text-sm">
                                                @foreach ($roles as $value => $label)
                                                    @if ($value !== \App\Enums\OrganizationRole::Owner->value || $currentRole === \App\Enums\OrganizationRole::Owner)
                                                        <option value="{{ $value }}" @selected($membership->role_code->value === $value)>{{ $label }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($protectedOwner)
                                        <span class="inline-flex rounded-full bg-[#E4F3F0] px-3 py-1 text-xs font-semibold text-[#2E8B74]">{{ $membership->status->label() }}</span>
                                    @else
                                        <select name="status" form="membership-update-{{ $membership->id }}" class="sgp-input py-2 text-sm">
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($membership->status->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ optional($membership->joined_at)->format('d/m/Y H:i') ?: 'Não informado' }}</p>
                                    @if ($membership->is_default)
                                        <p class="mt-1 text-xs font-semibold text-[#2E8B74]" title="Esta é a primeira organização aberta para esta pessoa ao entrar no SGP.">Padrão no login</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    @if (! $protectedOwner)
                                        <div class="sgp-membership-row-actions">
                                            @if ($membership->status === \App\Enums\OrganizationMembershipStatus::Active && $membership->user->is_active)
                                                <form method="POST" action="{{ route('organization-members.password-reset-link', $membership->id) }}" onsubmit="return confirm('Enviar um novo link de redefinição para o e-mail cadastrado?');">
                                                    @csrf
                                                    <button class="sgp-link whitespace-nowrap">Reenviar link</button>
                                                </form>
                                            @endif
                                            <button form="membership-update-{{ $membership->id }}" class="sgp-link whitespace-nowrap">Salvar alterações</button>
                                            <form method="POST" action="{{ route('organization-members.destroy', $membership->id) }}" onsubmit="return confirm('Remover este acesso? A conta e o histórico continuarão existindo, mas a pessoa perderá o acesso a esta organização.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-sm font-semibold text-[#A55252] hover:underline">Remover</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#667680]">Protegido</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhuma pessoa encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($memberships->hasPages())
                <div class="border-t border-[#DCE3E7] px-5 py-4">{{ $memberships->links() }}</div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#D7E6EA] bg-[#F8FAFB] p-5 text-sm text-[#36525E]">
            <h2 class="font-bold text-[#24313A]">O que cada nível permite</h2>
            <div class="sgp-role-guide">
                <p><strong>Administrador principal</strong><span>Controle total e proteção para a organização não ficar sem responsável.</span></p>
                <p><strong>Administrador da organização</strong><span>Gerencia a equipe e todos os projetos da própria organização.</span></p>
                <p><strong>Usuário da organização</strong><span>Visualiza e atua somente nos projetos em que participar.</span></p>
                <p><strong>Acesso de consulta</strong><span>Consulta apenas os projetos aos quais for adicionado, sem alterar conteúdo.</span></p>
            </div>
        </section>
    </div>
</x-app-layout>
