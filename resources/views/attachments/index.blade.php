<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Anexos</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if(session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-[#F0C7C7] bg-[#FFF4F4] px-4 py-3 text-sm text-[#A53E3E]">
                <p class="font-semibold">Não foi possível anexar o arquivo.</p>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @include('requirements._project-nav')

        <section class="grid gap-5 xl:grid-cols-3">
            @if ($canContribute)
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Adicionar arquivo</h2>
                <p class="mt-1 text-sm text-[#667680]">O anexo será armazenado em área privada e vinculado ao registro escolhido.</p>

                <form method="POST" action="{{ route('projects.attachments.store', $project) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="context" class="text-sm font-semibold text-[#24313A]">Vincular a</label>
                        <select id="context" name="context" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]" required>
                            @php
                                $groupedContexts = collect($contextOptions)->groupBy('group');
                            @endphp
                            @foreach($groupedContexts as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach($options as $option)
                                        <option value="{{ $option['value'] }}" @selected(old('context', 'project:'.$project->id) === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="file" class="text-sm font-semibold text-[#24313A]">Arquivo</label>
                        <input id="file" name="file" type="file" class="mt-1 block w-full rounded-lg border border-[#C9D3D9] bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-[#E8F3F6] file:px-3 file:py-2 file:font-semibold file:text-[#1D5D73]" required>
                        <p class="mt-2 text-xs leading-5 text-[#82919A]">Máximo: {{ number_format($maxUploadMb, 0, ',', '.') }} MB. Tipos: {{ implode(', ', $allowedExtensions) }}.</p>
                    </div>

                    <div>
                        <label for="description" class="text-sm font-semibold text-[#24313A]">Descrição <span class="font-normal text-[#82919A]">(opcional)</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="300" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]">{{ old('description') }}</textarea>
                    </div>

                    <button type="submit" class="sgp-button-primary w-full justify-center">Anexar arquivo</button>
                </form>
            </article>
            @endif

            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm xl:col-span-2">
                <div class="border-b border-[#DCE3E7] px-6 py-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-[#24313A]">Arquivos disponíveis</h2>
                            <p class="mt-1 text-sm text-[#667680]">Downloads liberados somente para participantes autorizados.</p>
                        </div>
                        <span class="rounded-full bg-[#E8F3F6] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $attachments->total() }} arquivo(s)</span>
                    </div>
                </div>

                @if($attachments->isEmpty())
                    <div class="px-6 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F3F6] text-xl text-[#1D5D73]">📎</div>
                        <p class="mt-4 font-semibold text-[#24313A]">Nenhum anexo disponível</p>
                        <p class="mt-1 text-sm text-[#667680]">Envie o primeiro arquivo usando o formulário ao lado.</p>
                    </div>
                @else
                    <div class="divide-y divide-[#E8EDF0]">
                        @foreach($attachments as $attachment)
                            <div class="px-6 py-5">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-all font-semibold text-[#24313A]">{{ $attachment->original_name }}</p>
                                        <p class="mt-1 text-xs font-medium text-[#287EA1]">{{ $attachment->context_label }}</p>
                                        @if($attachment->description)<p class="mt-2 text-sm text-[#667680]">{{ $attachment->description }}</p>@endif
                                        <p class="mt-2 text-xs text-[#82919A]">{{ $attachment->formattedSize() }} · Enviado por {{ $attachment->uploader->name }} em {{ $attachment->created_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <a href="{{ route('projects.attachments.download', [$project, $attachment]) }}" class="inline-flex rounded-lg border border-[#287EA1] px-3 py-2 text-xs font-semibold text-[#287EA1] hover:bg-[#EEF7FA]">Baixar</a>
                                        @if($attachment->can_remove)
                                            <form method="POST" action="{{ route('projects.attachments.destroy', [$project, $attachment]) }}" onsubmit="return confirm('Remover este anexo da consulta? O evento permanecerá no histórico.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex rounded-lg border border-[#E6B8B8] px-3 py-2 text-xs font-semibold text-[#A53E3E] hover:bg-[#FFF4F4]">Remover</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-[#DCE3E7] px-6 py-4">{{ $attachments->links() }}</div>
                @endif
            </article>
        </section>
    </div>
</x-app-layout>
