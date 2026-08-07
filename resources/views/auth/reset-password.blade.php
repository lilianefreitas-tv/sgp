<x-guest-layout>
    <div class="sgp-login-card">
        <p class="text-sm font-semibold uppercase tracking-wider text-[#287EA1]">Segurança da conta</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#24313A]">Criar nova senha</h2>
        <p class="mt-3 text-sm leading-6 text-[#667680]">Defina uma senha nova para concluir a recuperação do acesso.</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="sgp-field-label">E-mail</label>
                <input id="email" class="sgp-input" type="email" name="email" value="{{ old('email', $request->email) }}" required readonly autocomplete="username">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <label for="password" class="sgp-field-label">Nova senha</label>
                <input id="password" class="sgp-input" type="password" name="password" required autocomplete="new-password">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <label for="password_confirmation" class="sgp-field-label">Confirmar nova senha</label>
                <input id="password_confirmation" class="sgp-input" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="sgp-button-primary">Alterar senha</button>
        </form>
    </div>
</x-guest-layout>
