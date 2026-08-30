<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Senha temporária gerada</h1>
            <p class="mt-1 text-sm text-[#667680]">RM-004 · redefinição administrativa segura</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5" x-data="{ copied: false }">
        <section class="rounded-2xl border border-[#E8D5A7] bg-[#FFF9E9] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-bold uppercase tracking-wider text-[#7A5B18]">Exibição única</p>
            <h2 class="mt-2 text-xl font-bold text-[#24313A]">Copie a senha agora</h2>
            <p class="mt-2 text-sm leading-6 text-[#53636C]">O SGP não armazenará nem mostrará novamente esta senha. Entregue-a a <strong>{{ $managedUser->name }}</strong> por um canal seguro e separado.</p>

            <div class="mt-5 flex flex-col gap-3 rounded-xl border border-[#D7E6EA] bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <code id="temporary-password" class="break-all text-lg font-bold tracking-wide text-[#123B4A]">{{ $temporaryPassword }}</code>
                <button type="button" class="sgp-button-secondary sm:w-auto" @click="navigator.clipboard.writeText(document.getElementById('temporary-password').textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copiada' : 'Copiar senha'">Copiar senha</span>
                </button>
            </div>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <h2 class="font-bold text-[#24313A]">O que aconteceu</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-[#53636C]">
                <li>As sessões ativas da conta foram revogadas.</li>
                <li>Links anteriores de redefinição foram invalidados.</li>
                <li>No próximo acesso, o usuário será levado diretamente à criação da senha definitiva.</li>
                <li>A auditoria registra responsáveis, data e resultado, sem guardar a senha temporária.</li>
            </ul>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('platform.users.index') }}" class="sgp-button-primary sm:w-auto">Concluir e voltar</a>
        </div>
    </div>
</x-app-layout>
