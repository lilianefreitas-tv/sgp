<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Editar usuário da plataforma</h1>
            <p class="mt-1 text-sm text-[#667680]">Atualize a conta global sem alterar seus vínculos organizacionais</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('platform.users.update', $managedUser) }}">
                @csrf
                @method('PUT')
                @include('platform.users._form')
            </form>
        </div>

        <section class="rounded-2xl border border-[#D7E6EA] bg-[#F8FAFB] p-5">
            <h2 class="font-bold text-[#24313A]">Vínculos organizacionais</h2>
            <p class="mt-1 text-sm text-[#667680]">Estes vínculos são apenas informativos nesta tela. Altere-os dentro da Equipe da organização.</p>
            <div class="mt-4 space-y-2">
                @forelse ($managedUser->organizationMemberships as $membership)
                    <div class="rounded-lg border border-[#DCE3E7] bg-white px-4 py-3 text-sm">
                        <p class="font-semibold text-[#24313A]">{{ $membership->organization?->name ?? 'Organização indisponível' }}</p>
                        <p class="mt-1 text-xs text-[#667680]">{{ $membership->role_code->label() }} · {{ $membership->status->label() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-[#667680]">Esta conta não possui vínculo com nenhuma organização.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
