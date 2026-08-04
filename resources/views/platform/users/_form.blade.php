@php
    $nameField = isset($managedUser) ? 'name' : 'new_user_name';
    $emailField = isset($managedUser) ? 'email' : 'new_user_email';
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $nameField }}" class="sgp-field-label">Nome completo *</label>
        <input
            id="{{ $nameField }}"
            name="{{ $nameField }}"
            class="sgp-input"
            maxlength="255"
            value="{{ old($nameField, $managedUser->name ?? '') }}"
            required
            autofocus
        >
        @error($nameField)<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $emailField }}" class="sgp-field-label">E-mail *</label>
        <input
            id="{{ $emailField }}"
            name="{{ $emailField }}"
            type="email"
            class="sgp-input"
            maxlength="255"
            value="{{ old($emailField, $managedUser->email ?? '') }}"
            required
        >
        @error($emailField)<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="global_profile" class="sgp-field-label">Perfil global *</label>
        <select id="global_profile" name="global_profile" class="sgp-input" required>
            @foreach ($profiles as $value => $label)
                <option value="{{ $value }}" @selected(old('global_profile', isset($managedUser) ? $managedUser->global_profile->value : \App\Enums\GlobalProfile::User->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-xs leading-5 text-[#667680]">Superadmin administra toda a plataforma. Conta comum depende de vínculo com uma organização.</p>
        @error('global_profile')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
    </div>

    @isset($managedUser)
        <div>
            <label for="is_active" class="sgp-field-label">Situação *</label>
            <select id="is_active" name="is_active" class="sgp-input" required>
                <option value="1" @selected((string) old('is_active', (int) $managedUser->is_active) === '1')>Ativa</option>
                <option value="0" @selected((string) old('is_active', (int) $managedUser->is_active) === '0')>Inativa</option>
            </select>
            <p class="mt-2 text-xs leading-5 text-[#667680]">Inativar a conta bloqueia o login em todas as organizações, sem apagar histórico.</p>
            @error('is_active')<p class="mt-1 text-sm text-[#A55252]">{{ $message }}</p>@enderror
        </div>
    @else
        <div class="rounded-xl border border-[#D7E6EA] bg-[#F2F8FA] p-4 text-sm text-[#36525E]">
            <p class="font-semibold">Ativação segura</p>
            <p class="mt-1 text-xs leading-5">Após salvar, o SGP exibirá um link temporário para a pessoa definir a própria senha.</p>
        </div>
    @endisset
</div>

<div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('platform.users.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] px-5 py-3 text-sm font-semibold text-[#24313A] transition hover:bg-[#F5F7F9]">Cancelar</a>
    <button type="submit" class="sgp-button-primary sm:w-auto">{{ isset($managedUser) ? 'Salvar alterações' : 'Criar usuário' }}</button>
</div>
