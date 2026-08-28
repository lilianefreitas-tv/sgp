<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Documentação de origem</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $project->code }} · {{ $project->name }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('success'))
            <div class="sgp-alert sgp-alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="sgp-alert sgp-alert-error">{{ $errors->first() }}</div>
        @endif

        <section class="sgp-page-intro flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="sgp-page-kicker">P05.1 · PROJETO INCORPORADO</span>
                <h1 class="mt-2 text-2xl font-bold">O ponto de partida documental do projeto</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">Guarde contratos, TAP, visão, propostas e outros documentos que já existiam antes do acompanhamento pelo SGP.</p>
            </div>
            <a class="sgp-button-secondary whitespace-nowrap" href="{{ route('projects.show', $project) }}">Voltar ao projeto</a>
        </section>

        @if ($baseline)
            <section class="sgp-card p-5 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="sgp-page-kicker text-[#1D7A78]">Evolução desde {{ $baseline->code }}</p>
                        <h2 class="mt-2 text-xl font-bold text-[#24313A]">O que mudou desde a incorporação</h2>
                        <p class="mt-1 text-sm leading-6 text-[#667680]">A comparação usa o arquivo original e o SHA-256. Nenhuma alteração é inferida apenas pelo nome.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-amber-50 px-4 py-3"><strong class="block text-xl text-amber-800">{{ $evolution['counts']['updated'] }}</strong><span class="text-xs text-amber-700">Atualizados</span></div>
                        <div class="rounded-xl bg-cyan-50 px-4 py-3"><strong class="block text-xl text-cyan-800">{{ $evolution['counts']['added'] }}</strong><span class="text-xs text-cyan-700">Adicionados</span></div>
                        <div class="rounded-xl bg-emerald-50 px-4 py-3"><strong class="block text-xl text-emerald-800">{{ $evolution['counts']['unchanged'] }}</strong><span class="text-xs text-emerald-700">Inalterados</span></div>
                    </div>
                </div>

                <div class="mt-5 divide-y divide-[#E8EDF0] rounded-xl border border-[#DCE3E7]">
                    @forelse ($evolution['entries'] as $entry)
                        @php($badge = ['updated' => ['Atualizado', 'sgp-badge-warning'], 'added' => ['Adicionado depois', 'sgp-badge-info'], 'unchanged' => ['Sem alteração', 'sgp-badge-success']][$entry['status']])
                        <div class="grid gap-3 p-4 md:grid-cols-[1fr_auto] md:items-center">
                            <div>
                                <span class="sgp-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                                <h3 class="mt-2 font-bold text-[#24313A]">{{ $entry['title'] }}</h3>
                                <p class="mt-1 text-xs text-[#667680]">
                                    @if ($entry['reference'])
                                        Referência: v{{ $entry['reference']->origin_version }} · {{ $entry['reference']->declared_version ?: 'sem versão declarada' }}
                                    @else
                                        Não fazia parte da referência inicial
                                    @endif
                                    @if ($entry['latest'])
                                        · Vigente: v{{ $entry['latest']->origin_version }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($entry['reference'])
                                    <a class="sgp-action-link" href="{{ route('projects.attachments.download', [$project, $entry['reference']]) }}">Baixar referência</a>
                                @endif
                                @if ($entry['latest'] && (! $entry['reference'] || $entry['latest']->id !== $entry['reference']->id))
                                    <a class="sgp-action-link" href="{{ route('projects.attachments.download', [$project, $entry['latest']]) }}">Baixar vigente</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-sm text-[#667680]">Nenhum documento disponível para comparação.</div>
                    @endforelse
                </div>
            </section>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[380px_minmax(0,1fr)]">
            <aside class="space-y-6">
                @if ($canContribute)
                    <section class="sgp-card overflow-hidden">
                        <div class="border-b border-[#E8EDF0] bg-[#F8FAFB] px-5 py-4">
                            <h2 class="text-lg font-semibold">Adicionar documento</h2>
                            <p class="mt-1 text-sm text-slate-500">Inclua um arquivo novo ou registre outra versão.</p>
                        </div>
                        <form class="space-y-3 p-5" method="POST" action="{{ route('projects.origin-documents.store', $project) }}" enctype="multipart/form-data">@csrf
                            <label class="block rounded-xl border border-dashed border-[#B9C8D0] bg-[#F8FAFB] p-3"><span class="sgp-field-label">Arquivo</span><input class="mt-1 block w-full text-sm" type="file" name="file" required></label>
                            <label><span class="sgp-field-label">Título do documento</span><input class="sgp-input" name="origin_title" value="{{ old('origin_title') }}" required maxlength="200" placeholder="Ex.: Contrato de prestação de serviços"></label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label><span class="sgp-field-label">Categoria</span><select class="sgp-input" name="origin_category" required>@foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label><span class="sgp-field-label">Versão declarada</span><input class="sgp-input" name="declared_version" value="{{ old('declared_version') }}" placeholder="Ex.: 2.1"></label>
                                <label><span class="sgp-field-label">Referência externa</span><input class="sgp-input" name="external_reference" value="{{ old('external_reference') }}"></label>
                                <label><span class="sgp-field-label">Data original</span><input class="sgp-input" type="date" name="original_document_date" value="{{ old('original_document_date') }}"></label>
                            </div>
                            @if ($currentVersions->isNotEmpty())<label><span class="sgp-field-label">Nova versão de</span><select class="sgp-input" name="replaces_attachment_id"><option value="">Novo documento</option>@foreach ($currentVersions as $version)<option value="{{ $version->id }}">{{ $version->origin_title }} · v{{ $version->origin_version }}</option>@endforeach</select></label>@endif
                            <label><span class="sgp-field-label">Observação</span><textarea class="sgp-input" name="description" rows="2" placeholder="Informação complementar, se necessária">{{ old('description') }}</textarea></label>
                            <p class="text-xs text-slate-500">Permitidos: {{ implode(', ', $allowedExtensions) }} · até {{ number_format($maxUploadMb, 0) }} MB.</p>
                            <button class="sgp-button-primary w-full">Registrar documento</button>
                        </form>
                    </section>
                @endif

                <section class="sgp-card p-5">
                    <h2 class="text-lg font-semibold">Referência inicial</h2>
                    @if ($baseline)
                        <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900"><strong>{{ $baseline->code }}</strong><p class="mt-1">Constituída em {{ $baseline->established_at->format('d/m/Y H:i') }} por {{ $baseline->establishedBy->name }}.</p><p class="mt-2 break-all text-xs">SHA-256 {{ $baseline->checksum }}</p></div>
                    @elseif ($canManage && $currentVersions->isNotEmpty())
                        <p class="mt-1 text-sm text-slate-500">Marque o conjunto vigente recebido quando o SGP iniciou o acompanhamento.</p>
                        <form class="mt-4 space-y-3" method="POST" action="{{ route('projects.origin-baseline.store', $project) }}">@csrf
                            @foreach ($currentVersions as $version)<label class="flex gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" name="document_ids[]" value="{{ $version->id }}"><span class="text-sm"><strong>{{ $version->origin_title }}</strong><br>v{{ $version->origin_version }} · {{ $version->original_name }}</span></label>@endforeach
                            <label><span class="sgp-field-label">Finalidade da referência</span><textarea class="sgp-input" name="purpose" rows="2" placeholder="Ex.: acervo vigente recebido na incorporação"></textarea></label>
                            <button class="sgp-button-primary w-full">Constituir referência inicial</button>
                        </form>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Adicione documentos vigentes para constituir a referência inicial.</p>
                    @endif
                    <p class="mt-4 text-xs text-slate-500">Esta ação não declara que o SGP criou ou aprovou os documentos. Ela registra o ponto a partir do qual o projeto passou a ser acompanhado.</p>
                </section>
            </aside>

            <section class="sgp-card min-w-0 overflow-hidden">
                <div class="border-b border-[#E8EDF0] bg-[#F8FAFB] px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Acervo recebido</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $series->count() }} documento(s), com versões e integridade preservadas.</p>
                </div>

                @if ($series->isEmpty())
                    <div class="m-5 sgp-empty-state">
                        <strong>Nenhum documento de origem ainda</strong>
                        <span class="mt-2 text-sm text-slate-500">Use o formulário ao lado para registrar o material recebido.</span>
                    </div>
                @else
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full min-w-[860px] table-fixed text-left text-sm">
                            <thead class="border-b border-[#DCE3E7] bg-white text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-[25%] px-4 py-3">Título do documento</th>
                                    <th class="w-[15%] px-4 py-3">Categoria</th>
                                    <th class="w-[14%] px-4 py-3">Versão declarada</th>
                                    <th class="w-[18%] px-4 py-3">Referência externa</th>
                                    <th class="w-[13%] px-4 py-3">Data original</th>
                                    <th class="w-[15%] px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E8EDF0]">
                                @foreach ($series as $versions)
                                    @php($current = $versions->firstWhere('origin_status', 'current') ?? $versions->first())
                                    <tr class="align-top transition hover:bg-[#F8FAFB]">
                                        <td class="px-4 py-4">
                                            <strong class="block break-words text-slate-900">{{ $current->origin_title }}</strong>
                                            <span class="mt-1 block break-words text-xs text-slate-500">{{ $current->original_name }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-slate-700">{{ $categories[$current->origin_category] ?? $current->origin_category }}</td>
                                        <td class="px-4 py-4"><span class="sgp-badge sgp-badge-success">{{ $current->declared_version ?: 'Não informada' }}</span></td>
                                        <td class="break-words px-4 py-4 text-slate-700">{{ $current->external_reference ?: 'Não informada' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-slate-700">{{ $current->original_document_date?->format('d/m/Y') ?? 'Não informada' }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <a class="sgp-link whitespace-nowrap" href="{{ route('projects.attachments.download', [$project, $current]) }}">Baixar</a>
                                            @if ($versions->count() > 1)
                                                <details class="relative mt-2 text-left">
                                                    <summary class="cursor-pointer list-none text-right text-xs font-semibold text-sky-800">Histórico ({{ $versions->count() }})</summary>
                                                    <div class="mt-2 space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                                        @foreach ($versions as $version)
                                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                                <span>v{{ $version->origin_version }} · {{ $version->declared_version ?: 'sem declaração' }}</span>
                                                                <a class="sgp-link" href="{{ route('projects.attachments.download', [$project, $version]) }}">Baixar</a>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3 p-4 lg:hidden">
                        @foreach ($series as $versions)
                            @php($current = $versions->firstWhere('origin_status', 'current') ?? $versions->first())
                            <article class="rounded-xl border border-[#DCE3E7] bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div><strong class="text-slate-900">{{ $current->origin_title }}</strong><p class="mt-1 text-xs text-slate-500">{{ $current->original_name }}</p></div>
                                    <a class="sgp-link whitespace-nowrap" href="{{ route('projects.attachments.download', [$project, $current]) }}">Baixar</a>
                                </div>
                                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                    <div><dt class="text-xs text-slate-500">Categoria</dt><dd class="mt-1 text-slate-800">{{ $categories[$current->origin_category] ?? $current->origin_category }}</dd></div>
                                    <div><dt class="text-xs text-slate-500">Versão declarada</dt><dd class="mt-1 text-slate-800">{{ $current->declared_version ?: 'Não informada' }}</dd></div>
                                    <div><dt class="text-xs text-slate-500">Referência externa</dt><dd class="mt-1 break-words text-slate-800">{{ $current->external_reference ?: 'Não informada' }}</dd></div>
                                    <div><dt class="text-xs text-slate-500">Data original</dt><dd class="mt-1 text-slate-800">{{ $current->original_document_date?->format('d/m/Y') ?? 'Não informada' }}</dd></div>
                                </dl>
                                @if ($versions->count() > 1)
                                    <details class="mt-4 border-t border-slate-100 pt-3 text-sm"><summary class="cursor-pointer font-medium text-sky-800">Ver histórico ({{ $versions->count() }} versões)</summary>
                                        <div class="mt-2 space-y-2">@foreach ($versions as $version)<div class="flex justify-between rounded-lg bg-slate-50 p-3"><span>v{{ $version->origin_version }} · {{ $version->declared_version ?: 'sem declaração' }}</span><a class="sgp-link" href="{{ route('projects.attachments.download', [$project, $version]) }}">Baixar</a></div>@endforeach</div>
                                    </details>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
