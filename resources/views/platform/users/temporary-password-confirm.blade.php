<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Confirmar redefinição administrativa</h1>
            <p class="mt-1 text-sm text-[#667680]">RM-004 · ação sensível e auditada</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <section class="rounded-2xl border border-[#E8D5A7] bg-[#FFF9E9] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-bold uppercase tracking-wider text-[#7A5B18]">Atenção</p>
            <h2 class="mt-2 text-xl font-bold text-[#24313A]">Gerar senha temporária para {{ $managedUser->name }}?</h2>
            <p class="mt-2 text-sm leading-6 text-[#53636C]">A senha atual deixará de funcionar, todas as sessões serão revogadas e a nova senha temporária será exibida uma única vez. No próximo acesso, o usuário deverá cadastrar uma senha definitiva.</p>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-[#667680]">Usuário</dt><dd class="mt-1 font-bold text-[#24313A]">{{ $managedUser->name }}</dd></div>
                <div><dt class="font-semibold text-[#667680]">E-mail</dt><dd class="mt-1 break-all font-bold text-[#24313A]">{{ $managedUser->email }}</dd></div>
            </dl>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('platform.users.index') }}" class="sgp-button-secondary sm:w-auto">Cancelar</a>
            <form method="POST" action="{{ route('platform.users.temporary-password', $managedUser) }}" onsubmit="return confirm('Confirma a redefinição e a revogação das sessões desta conta?');">
                @csrf
                <button class="sgp-button-primary sm:w-auto">Gerar senha temporária</button>
            </form>
        </div>
    </div>
</x-app-layout>
