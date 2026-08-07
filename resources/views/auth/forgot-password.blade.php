<x-guest-layout>
    <div class="sgp-login-card">
        <p class="text-sm font-semibold uppercase tracking-wider text-[#287EA1]">Recuperação de acesso</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#24313A]">Esqueceu a senha?</h2>
        <p class="mt-3 text-sm leading-6 text-[#667680]">Informe seu e-mail. Se houver uma conta ativa, enviaremos um link temporário para você criar uma nova senha.</p>

        <x-auth-session-status class="mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="mt-6">
            @csrf
            <div>
                <label for="email" class="sgp-field-label">E-mail</label>
                <input id="email" class="sgp-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <button class="sgp-button-primary mt-6">Enviar link de redefinição</button>
            <div class="mt-5 text-center">
                <a href="{{ route('login') }}" class="sgp-link">Voltar para o login</a>
            </div>
        </form>
    </div>
</x-guest-layout>
