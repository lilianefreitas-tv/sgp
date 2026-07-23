<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-bold text-[#24313A]">Comentários</h1>
            <p class="mt-1 text-sm text-[#667680]">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if(session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif

        @include('requirements._project-nav')

        <section class="grid gap-5 xl:grid-cols-3">
            <article class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Novo comentário</h2>
                <p class="mt-1 text-sm text-[#667680]">Registre uma observação no projeto, em um requisito ou em uma tarefa.</p>

                <form method="POST" action="{{ route('projects.comments.store', $project) }}" class="mt-5 space-y-4">
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
                        @error('context')<p class="mt-1 text-xs font-medium text-[#C44B4B]">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="body" class="text-sm font-semibold text-[#24313A]">Comentário</label>
                        <textarea id="body" name="body" rows="7" maxlength="5000" class="mt-1 block w-full rounded-lg border-[#C9D3D9] text-sm focus:border-[#287EA1] focus:ring-[#287EA1]" placeholder="Escreva uma decisão, observação, orientação ou ponto de atenção..." required>{{ old('body') }}</textarea>
                        <p class="mt-1 text-xs text-[#82919A]">Até 5.000 caracteres.</p>
                        @error('body')<p class="mt-1 text-xs font-medium text-[#C44B4B]">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="sgp-button-primary w-full justify-center">Registrar comentário</button>
                </form>
            </article>

            <article class="rounded-2xl border border-[#DCE3E7] bg-white shadow-sm xl:col-span-2">
                <div class="border-b border-[#DCE3E7] px-6 py-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-[#24313A]">Comentários registrados</h2>
                            <p class="mt-1 text-sm text-[#667680]">Comunicação contextual preservada no histórico do projeto.</p>
                        </div>
                        <span class="rounded-full bg-[#E8F3F6] px-3 py-1 text-xs font-semibold text-[#1D5D73]">{{ $comments->total() }} registro(s)</span>
                    </div>
                </div>

                @if($comments->isEmpty())
                    <div class="px-6 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F3F6] text-xl text-[#1D5D73]">💬</div>
                        <p class="mt-4 font-semibold text-[#24313A]">Nenhum comentário registrado</p>
                        <p class="mt-1 text-sm text-[#667680]">O primeiro comentário aparecerá aqui.</p>
                    </div>
                @else
                    <div class="divide-y divide-[#E8EDF0]">
                        @foreach($comments as $comment)
                            <div class="px-6 py-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#24313A]">{{ $comment->author->name }}</p>
                                        <p class="mt-1 text-xs font-medium text-[#287EA1]">{{ $comment->context_label }}</p>
                                    </div>
                                    <time class="text-xs text-[#667680]" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('d/m/Y H:i') }}</time>
                                </div>
                                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#3D4B53]">{{ $comment->body }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-[#DCE3E7] px-6 py-4">{{ $comments->links() }}</div>
                @endif
            </article>
        </section>
    </div>
</x-app-layout>
