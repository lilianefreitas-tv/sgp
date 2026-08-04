@csrf
@if ($organization->exists)
    @method('PUT')
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <div>
        <label for="name" class="sgp-field-label">Nome *</label>
        <input id="name" name="name" class="sgp-input" required maxlength="180" value="{{ old('name', $organization->name) }}">
        @error('name')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="slug" class="sgp-field-label">Identificador</label>
        <input id="slug" name="slug" class="sgp-input" maxlength="120" value="{{ old('slug', $organization->slug) }}" placeholder="Gerado automaticamente pelo nome">
        <p class="mt-1 text-xs text-[#667680]">Use letras minúsculas, números e hífens. Se ficar vazio, será gerado pelo nome.</p>
        @error('slug')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="sgp-field-label">Tipo *</label>
        <select id="type" name="type" class="sgp-input" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $organization->type?->value ?? \App\Enums\OrganizationType::Company->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="timezone" class="sgp-field-label">Fuso horário *</label>
        <input id="timezone" name="timezone" class="sgp-input" required maxlength="60" value="{{ old('timezone', $organization->timezone ?: 'America/Belem') }}">
        @error('timezone')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    @if (! $organization->exists)
        <div class="lg:col-span-2 rounded-xl border border-[#D7E6EA] bg-[#F8FAFB] p-5" x-data="{ accountMode: @js(old('account_mode', 'new')) }">
            <div>
                <h2 class="text-base font-bold text-[#24313A]">Administrador principal</h2>
                <p class="mt-1 text-sm text-[#667680]">Defina a primeira pessoa responsável pela organização. Ela não receberá acesso de Superadmin.</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <label class="inline-flex items-center gap-2 rounded-lg border border-[#CAD5DA] bg-white px-4 py-2 text-sm font-semibold text-[#36525E]">
                    <input type="radio" name="account_mode" value="new" x-model="accountMode"> Criar nova conta
                </label>
                <label class="inline-flex items-center gap-2 rounded-lg border border-[#CAD5DA] bg-white px-4 py-2 text-sm font-semibold text-[#36525E]">
                    <input type="radio" name="account_mode" value="existing" x-model="accountMode"> Usar conta existente
                </label>
            </div>

            <div class="mt-5" x-show="accountMode === 'existing'" x-cloak>
                <label for="administrator_user_id" class="sgp-field-label">Conta ativa *</label>
                <select id="administrator_user_id" name="administrator_user_id" class="sgp-input" :required="accountMode === 'existing'">
                    <option value="">Selecione</option>
                    @foreach ($administratorCandidates as $candidate)
                        <option value="{{ $candidate->id }}" @selected((string) old('administrator_user_id') === (string) $candidate->id)>{{ $candidate->name }} · {{ $candidate->email }}</option>
                    @endforeach
                </select>
                @error('administrator_user_id')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2" x-show="accountMode === 'new'" x-cloak>
                <div>
                    <label for="new_user_name" class="sgp-field-label">Nome *</label>
                    <input id="new_user_name" name="new_user_name" class="sgp-input" maxlength="255" value="{{ old('new_user_name') }}" :required="accountMode === 'new'">
                    @error('new_user_name')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new_user_email" class="sgp-field-label">E-mail *</label>
                    <input id="new_user_email" type="email" name="new_user_email" class="sgp-input" maxlength="255" value="{{ old('new_user_email') }}" :required="accountMode === 'new'">
                    @error('new_user_email')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
                </div>
                <p class="sm:col-span-2 text-xs leading-5 text-[#667680]">O sistema criará a conta como comum e apresentará um link temporário para a pessoa definir a própria senha.</p>
            </div>
        </div>
    @else
        <div>
            <label for="status" class="sgp-field-label">Situação *</label>
            <select id="status" name="status" class="sgp-input" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $organization->status->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
        </div>

        <div>
            <span class="sgp-field-label">Administradores principais ativos</span>
            <div class="rounded-lg border border-[#DCE3E7] bg-[#F8FAFB] px-4 py-3 text-sm text-[#24313A]">
                @forelse ($organization->memberships as $owner)
                    <p class="font-semibold">{{ $owner->user->name }}</p>
                    <p class="text-xs text-[#667680]">{{ $owner->user->email }}</p>
                @empty
                    <p class="font-semibold text-[#A55252]">Nenhum Administrador principal ativo</p>
                @endforelse
            </div>
        </div>
    @endif
</div>

<div class="mt-6 flex flex-wrap justify-end gap-3">
    <a href="{{ route('platform.organizations.index') }}" class="rounded-lg border border-[#CAD5DA] px-5 py-2.5 text-sm font-semibold text-[#53636C] hover:bg-[#F5F7F9]">Cancelar</a>
    <button class="sgp-button-primary">{{ $organization->exists ? 'Salvar alterações' : 'Criar organização' }}</button>
</div>
