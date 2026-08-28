<x-app-layout>
    <x-slot name="header"><h1 class="font-semibold">Contratos</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 p-6">
        <section class="sgp-page-intro flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="sgp-page-kicker">Contratos e instrumentos</p><h1 class="mt-2 text-2xl font-bold">{{ $project ? 'Contratos do projeto '.$project->code : 'Compromissos que orientam projetos' }}</h1><p class="mt-2 text-sm leading-6 text-white/80">Registre somente quando houver contrato. Projetos internos continuam simples.</p></div>
            <div class="flex flex-wrap gap-3">@if($project)<a href="{{ route('projects.show',$project) }}" class="sgp-button-secondary shrink-0 sm:w-auto">Voltar ao projeto</a>@endif<a href="{{ route('contracts.create',$project ? ['project'=>$project->id] : []) }}" class="sgp-button-secondary shrink-0 sm:w-auto">Novo contrato</a></div>
        </section>

        @forelse($contracts as $contract)
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-between"><div><p class="text-xs text-[#55707B]">{{ $contract->code }} · {{ $contract->status->label() }}</p><h2 class="mt-1 font-bold">{{ $contract->title }}</h2><p class="mt-2 text-sm text-[#667680]">{{ $contract->object }}</p><div class="mt-3 flex flex-wrap gap-2 text-xs">@if($contract->initiative)<span class="rounded-full bg-[#EDF8F5] px-3 py-1 font-semibold text-[#256C5C]">{{ $contract->initiative->code }}</span>@endif @if($contract->project)<span class="rounded-full bg-[#EDF6F8] px-3 py-1 font-semibold text-[#1D5D73]">{{ $contract->project->code }}</span>@else<span class="rounded-full bg-[#F3F5F6] px-3 py-1 font-semibold text-[#667680]">Sem projeto</span>@endif</div></div><a class="font-semibold text-[#006B88]" href="{{ route('contracts.show',$contract) }}">Abrir →</a></div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed p-10 text-center">Nenhum contrato cadastrado. Isso é normal quando o projeto não exige instrumento contratual.</div>
        @endforelse
        {{ $contracts->links() }}
    </div>
</x-app-layout>
