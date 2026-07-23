<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
            </div>
            <h1 class="mt-1 truncate text-xl font-bold text-[#24313A]">Documentos de {{ $project->name }}</h1>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-xl border border-[#BFE2D9] bg-[#EDF8F5] px-4 py-3 text-sm font-medium text-[#256C5C]">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl border border-[#F0D5A9] bg-[#FFF8EC] px-4 py-3 text-sm font-medium text-[#8A5B13]">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @include('requirements._project-nav')

        @if ($canGenerate && ! $visionReady)
            <section class="flex flex-col gap-4 rounded-2xl border border-[#F0D5A9] bg-[#FFF8EC] p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-[#7A5115]">Complete a base do Documento de Visão</h2>
                    <p class="mt-1 text-sm text-[#8A6A3C]">Contexto, problema, solução, público-alvo e escopo precisam ser informados uma única vez.</p>
                </div>
                <a href="{{ route('projects.documents.setup.edit', $project) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#D89427] px-5 py-3 text-sm font-semibold text-white hover:bg-[#BE7E1F]">Preencher informações</a>
            </section>
        @endif

        <section>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-[#24313A]">Gerar novo documento</h2>
                    <p class="mt-1 text-sm text-[#667680]">O DOCX e o PDF serão criados juntos e armazenados na mesma versão.</p>
                </div>
                @if ($canGenerate)
                    <a href="{{ route('projects.documents.setup.edit', $project) }}" class="text-sm font-semibold text-[#1D5D73] hover:underline">Revisar informações documentais</a>
                @endif
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($types as $type)
                    @php
                        $typeTemplates = $templates->get($type->value, collect());
                        $disabled = ! $canGenerate || $typeTemplates->isEmpty() || ($type === \App\Enums\DocumentType::Vision && ! $visionReady);
                    @endphp
                    <article class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="rounded-xl bg-[#E6F0F3] p-3 text-[#1D5D73]">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l4 4v14H7V3Zm7 0v5h5M10 12h5M10 16h5"/></svg>
                            </div>
                            <span class="rounded-full bg-[#F3F5F6] px-3 py-1 text-xs font-semibold text-[#667680]">{{ $type->shortCode() }}</span>
                        </div>
                        <h3 class="mt-4 font-bold text-[#24313A]">{{ $type->label() }}</h3>
                        <p class="mt-2 min-h-10 text-sm leading-5 text-[#667680]">
                            @if ($type === \App\Enums\DocumentType::Vision)
                                Consolida visão, contexto, problema, solução, escopo e equipe.
                            @elseif ($type === \App\Enums\DocumentType::RequirementsList)
                                Reúne requisitos, versões, prioridades, responsáveis e critérios de aceite.
                            @else
                                Reúne tarefas, vínculos, responsáveis, estimativas, prazos e situação.
                            @endif
                        </p>

                        <form method="POST" action="{{ route('projects.documents.generate', $project) }}" class="mt-5 space-y-3">
                            @csrf
                            <label for="template-{{ $type->value }}" class="sgp-field-label">Modelo</label>
                            <select id="template-{{ $type->value }}" name="document_template_id" class="sgp-input" required @disabled($disabled)>
                                @if ($typeTemplates->isEmpty())
                                    <option value="">Nenhum modelo ativo</option>
                                @else
                                @foreach ($typeTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} · v{{ $template->version }}</option>
                                @endforeach
                                @endif
                            </select>
                            <button class="sgp-button-primary" @disabled($disabled)>Gerar DOCX e PDF</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#DCE3E7] bg-white shadow-sm">
            <div class="border-b border-[#DCE3E7] px-6 py-5">
                <h2 class="text-base font-bold text-[#24313A]">Histórico de documentos</h2>
                <p class="mt-1 text-sm text-[#667680]">Cada geração permanece disponível como uma versão independente.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8EDF0]">
                    <thead class="bg-[#F8FAFB]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-[#667680]">
                            <th class="px-6 py-4">Documento</th>
                            <th class="px-6 py-4">Versão</th>
                            <th class="px-6 py-4">Modelo</th>
                            <th class="px-6 py-4">Gerado por</th>
                            <th class="px-6 py-4">Data</th>
                            <th class="px-6 py-4 text-right">Downloads</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EDF0]">
                        @if ($documents->isEmpty())
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-[#667680]">Nenhum documento foi gerado neste projeto.</td></tr>
                        @else
                        @foreach ($documents as $document)
                            <tr class="text-sm text-[#24313A]">
                                <td class="px-6 py-4"><p class="font-semibold">{{ $document->title }}</p><p class="mt-1 text-xs text-[#667680]">{{ $document->type->shortCode() }} · {{ $document->metadata['project_code'] ?? $project->code }}</p></td>
                                <td class="px-6 py-4"><span class="rounded-full bg-[#E6F0F3] px-3 py-1 text-xs font-bold text-[#1D5D73]">{{ $document->versionLabel() }}</span></td>
                                <td class="px-6 py-4">{{ $document->template->name }}</td>
                                <td class="px-6 py-4">{{ $document->generator->name }}</td>
                                <td class="px-6 py-4">{{ $document->generated_at->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('projects.documents.download', [$project, $document, 'docx']) }}" class="rounded-lg border border-[#287EA1] px-3 py-2 text-xs font-bold text-[#287EA1] hover:bg-[#EDF6F8]">DOCX</a>
                                        <a href="{{ route('projects.documents.download', [$project, $document, 'pdf']) }}" class="rounded-lg border border-[#2E8B74] px-3 py-2 text-xs font-bold text-[#2E8B74] hover:bg-[#EDF8F5]">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($documents->hasPages())
                <div class="border-t border-[#E8EDF0] px-6 py-4">{{ $documents->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
