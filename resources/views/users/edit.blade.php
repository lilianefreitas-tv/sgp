<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Editar usuário</h1>
            <p class="mt-1 text-sm text-[#667680]">Atualize dados, perfil global e situação de acesso</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('users.update', $managedUser) }}">
                @csrf
                @method('PUT')
                @include('users._form')
            </form>
        </div>
    </div>
</x-app-layout>
