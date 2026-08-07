<x-guest-layout>
    <div class="sgp-login-card">
        <div class="mb-8">
            <p
                class="mb-2 text-sm font-semibold uppercase tracking-wider
                       text-[#287EA1]"
            >
                Acesso ao sistema
            </p>

            <h2 class="text-3xl font-bold tracking-tight text-[#24313A]">
                Bem-vindo(a)!
            </h2>

            <p class="mt-3 text-sm leading-6 text-[#667680]">
                Informe seus dados para acessar o ambiente de gestão.
            </p>
        </div>

        <x-auth-session-status
            class="mb-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm
                   text-emerald-800"
            :status="session('status')"
        />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <label for="email" class="sgp-field-label">
                    E-mail
                </label>

                <input
                    id="email"
                    class="sgp-input"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="nome@instituicao.br"
                    required
                    autofocus
                    autocomplete="username"
                >

                @error('email')
                    <p class="mt-2 text-sm text-[#C44B4B]">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="password" class="sgp-field-label">
                    Senha
                </label>

                <input
                    id="password"
                    class="sgp-input"
                    type="password"
                    name="password"
                    placeholder="Digite sua senha"
                    required
                    autocomplete="current-password"
                >

                @error('password')
                    <p class="mt-2 text-sm text-[#C44B4B]">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5 flex items-center justify-between gap-4">
                <label
                    for="remember_me"
                    class="inline-flex cursor-pointer items-center"
                >
                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="rounded border-gray-300 text-[#123B4A]
                               shadow-sm focus:ring-[#287EA1]"
                    >

                    <span class="ms-2 text-sm text-[#667680]">
                        Lembrar de mim
                    </span>
                </label>

                @if (Route::has('password.request'))
                    <a
                        class="sgp-link"
                        href="{{ route('password.request') }}"
                    >
                        Esqueceu a senha?
                    </a>
                @endif
            </div>

            <button type="submit" class="sgp-button-primary mt-7">
                Entrar
            </button>
        </form>

        <div
            class="mt-8 border-t border-[#DCE3E7] pt-5 text-center
                   text-xs text-[#667680]"
        >
            <p>SGP • Sistema de Gestão de Projetos de Software</p>
            <p class="mt-1">{{ config('sgp.release_label') }}</p>
        </div>
    </div>
</x-guest-layout>
