<x-guest-layout>
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-wider text-[#287EA1]">Proteção da conta</p>
        <h1 class="mt-2 text-xl font-bold text-[#24313A]">Crie sua senha definitiva</h1>
        <p class="mt-2 text-sm leading-6 text-[#667680]">A senha atual é temporária e só permite concluir esta troca. Depois disso, você poderá acessar normalmente o PRISMA SGP.</p>
    </div>

    <form method="POST" action="{{ route('password.required.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" value="Senha temporária" />
            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required autofocus />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Nova senha" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirme a nova senha" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
        </div>

        <button class="sgp-button-primary w-full">Salvar senha definitiva</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button class="text-sm font-semibold text-[#667680] hover:text-[#123B4A] hover:underline">Sair da conta</button>
    </form>
</x-guest-layout>
