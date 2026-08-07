<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Novo usuário da plataforma</h1>
            <p class="mt-1 text-sm text-[#667680]">Crie uma conta global sem vinculá-la automaticamente a uma organização</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('platform.users.store') }}">
                @csrf
                @include('platform.users._form')
            </form>
        </div>
    </div>
</x-app-layout>
