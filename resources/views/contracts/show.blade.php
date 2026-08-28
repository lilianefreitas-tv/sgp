<x-app-layout>
    <x-slot name="header"><h1 class="font-semibold">{{ $contract->code }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 p-6">
        @if(session('success'))<div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="sgp-page-intro">
            <p class="sgp-page-kicker">{{ $contract->status->label() }} · versão {{ $contract->versions->max('version') }}</p>
            <h1 class="mt-2 text-2xl font-bold">{{ $contract->title }}</h1>
            <p class="mt-2 text-sm leading-6 text-white/80">{{ $contract->object }}</p>
        </section>

        <section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2">
                <div><p class="text-xs font-bold uppercase tracking-wider text-[#667680]">Iniciativa</p>@if($contract->initiative)<a class="mt-2 inline-block font-semibold text-[#2E8B74]" href="{{ $contract->initiative->origin->value === 'commercial' ? route('commercial.show',$contract->initiative) : route('initiatives.conversion.show',$contract->initiative) }}">{{ $contract->initiative->code }} · {{ $contract->initiative->title }}</a>@else<p class="mt-2 text-sm text-[#667680]">Sem iniciativa vinculada.</p>@endif</div>
                <div><p class="text-xs font-bold uppercase tracking-wider text-[#667680]">Projeto</p>@if($contract->project)<a class="mt-2 inline-block font-semibold text-[#287EA1]" href="{{ route('projects.show',$contract->project) }}">{{ $contract->project->code }} · {{ $contract->project->name }}</a>@else<p class="mt-2 text-sm text-[#667680]">Contrato ainda independente de projeto.</p>@endif</div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <main class="space-y-6 lg:col-span-2">
                <section class="rounded-2xl border bg-white p-6"><h2 class="font-bold">Conteúdo contratual</h2><div class="prose mt-4 max-w-none">{!! filled($contract->content) ? $contract->content : '<p class="text-gray-500">Nenhum texto contratual foi registrado nesta versão.</p>' !!}</div></section>
                <section class="rounded-2xl border bg-white p-6"><h2 class="font-bold">Documentos relacionados</h2><div class="mt-4 divide-y">@forelse($contract->attachments as $file)<div class="flex items-center justify-between gap-4 py-3"><div><span>{{ $file->original_name }}</span>@if(! $file->file_available)<p class="mt-1 text-xs font-medium text-[#A53E3E]">Arquivo ausente do armazenamento. Registre uma nova versão e reenvie o documento.</p>@endif</div>@if($file->file_available)<a class="font-semibold text-[#006B88]" href="{{ route('contracts.attachments.download',[$contract,$file]) }}">Baixar</a>@else<span class="rounded-lg bg-[#FFF4F4] px-3 py-2 text-xs font-semibold text-[#A53E3E]">Indisponível</span>@endif</div>@empty<p class="text-sm text-gray-500">Nenhum anexo.</p>@endforelse</div></section>
            </main>

            <aside class="space-y-4">
                <a href="{{ route('contracts.edit',$contract) }}" class="block rounded-lg bg-[#155363] px-5 py-3 text-center font-bold text-white">Registrar nova versão</a>
                @if(!$contract->project && !$contract->initiative)
                    <a href="{{ route('projects.create',['contract'=>$contract->id]) }}" class="block rounded-lg border border-[#155363] px-5 py-3 text-center font-bold text-[#155363]">Criar projeto a partir deste contrato</a>
                @elseif(!$contract->project && $contract->initiative)
                    <a href="{{ route('initiatives.conversion.show',$contract->initiative) }}" class="block rounded-lg border border-[#2E8B74] px-5 py-3 text-center font-bold text-[#256C5C]">Abrir conversão da iniciativa</a>
                @endif

                @if(!$contract->project && $availableProjects->isNotEmpty())
                    <section class="rounded-2xl border border-[#BFD7DF] bg-[#F4F9FA] p-5">
                        <h2 class="font-bold text-[#123B4A]">Vincular a projeto existente</h2>
                        <p class="mt-1 text-xs leading-5 text-[#667680]">O vínculo gera nova versão e não permite transferência silenciosa.</p>
                        <form method="POST" action="{{ route('contracts.project.link',$contract) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
                            <select name="project_id" class="sgp-input" required><option value="">Selecione o projeto</option>@foreach($availableProjects as $projectOption)<option value="{{ $projectOption->id }}">{{ $projectOption->code }} · {{ $projectOption->name }}</option>@endforeach</select>
                            <button class="sgp-button-primary">Vincular contrato</button>
                        </form>
                    </section>
                @endif

                <section class="rounded-2xl border bg-white p-5"><h2 class="font-bold">Histórico</h2>@foreach($contract->versions->sortByDesc('version') as $version)<div class="mt-3 border-t pt-3 text-sm"><strong>Versão {{ $version->version }}</strong><br>{{ $version->reason }}</div>@endforeach</section>
            </aside>
        </div>
    </div>
</x-app-layout>
