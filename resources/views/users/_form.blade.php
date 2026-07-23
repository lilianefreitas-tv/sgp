<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="sgp-field-label">Nome completo</label>
        <input id="name" name="name" type="text" class="sgp-input"
               value="{{ old('name', $managedUser->name ?? '') }}" required autofocus>
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <label for="email" class="sgp-field-label">E-mail</label>
        <input id="email" name="email" type="email" class="sgp-input"
               value="{{ old('email', $managedUser->email ?? '') }}" required>
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <label for="global_profile" class="sgp-field-label">Perfil global</label>
        <select id="global_profile" name="global_profile" class="sgp-input" required>
            @foreach ($profiles as $value => $label)
                <option value="{{ $value }}" @selected(old('global_profile', isset($managedUser) ? $managedUser->global_profile->value : 'user') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <p class="mt-2 text-xs text-[#667680]">O papel exercido em cada projeto será definido separadamente.</p>
        <x-input-error :messages="$errors->get('global_profile')" class="mt-2" />
    </div>

    <div>
        <label for="is_active" class="sgp-field-label">Situação</label>
        <select id="is_active" name="is_active" class="sgp-input" required>
            <option value="1" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '1')>Ativo</option>
            <option value="0" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '0')>Inativo</option>
        </select>
        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
    </div>

    <div>
        <label for="password" class="sgp-field-label">Senha {{ isset($managedUser) ? '(opcional)' : '' }}</label>
        <input id="password" name="password" type="password" class="sgp-input"
               {{ isset($managedUser) ? '' : 'required' }} autocomplete="new-password">
        @isset($managedUser)
            <p class="mt-2 text-xs text-[#667680]">Deixe em branco para manter a senha atual.</p>
        @endisset
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <label for="password_confirmation" class="sgp-field-label">Confirmar senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password"
               class="sgp-input" {{ isset($managedUser) ? '' : 'required' }} autocomplete="new-password">
    </div>
</div>

<div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('users.index') }}"
       class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] px-5 py-3 text-sm font-semibold text-[#24313A] transition hover:bg-[#F5F7F9]">
        Cancelar
    </a>
    <button type="submit" class="sgp-button-primary sm:w-auto">
        {{ isset($managedUser) ? 'Salvar alterações' : 'Cadastrar usuário' }}
    </button>
</div>
