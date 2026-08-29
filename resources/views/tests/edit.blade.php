<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold text-[#287EA1]">{{ $testCase->code }}</p><h1 class="mt-1 text-xl font-bold text-[#24313A]">Editar caso de teste</h1></div></x-slot>
    <form method="POST" action="{{ route('projects.tests.update', [$project, $testCase]) }}" class="space-y-5">@csrf @method('PUT')
        @include('requirements._project-nav')
        @if($errors->any())<div class="rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @include('tests._form')
        <div class="flex gap-3"><a class="inline-flex items-center rounded-lg border border-[#DCE3E7] bg-white px-4 py-2.5 text-sm font-semibold" href="{{ route('projects.tests.show', [$project, $testCase]) }}">Cancelar</a><button class="sgp-button-primary w-auto px-5">Salvar alterações</button></div>
    </form>
</x-app-layout>
