<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold text-[#287EA1]">{{ $project->code }}</p>
            <h1 class="mt-1 text-xl font-bold text-[#24313A]">Informações para o Documento de Visão</h1>
            <p class="mt-1 text-sm text-[#667680]">Esses dados ficam salvos no projeto e poderão ser atualizados antes de qualquer nova versão.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('warning'))
            <div class="rounded-xl border border-[#F0D5A9] bg-[#FFF8EC] px-4 py-3 text-sm font-medium text-[#8A5B13]">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('projects.documents.setup.update', $project) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <div class="grid gap-6">
                    <div>
                        <label for="document_context" class="sgp-field-label">Contexto *</label>
                        <textarea id="document_context" name="document_context" rows="5" class="sgp-input" required placeholder="Descreva o cenário atual, a rotina, os envolvidos e as dificuldades que motivaram o projeto.">{{ old('document_context', $project->document_context) }}</textarea>
                    </div>
                    <div>
                        <label for="problem_statement" class="sgp-field-label">Problema *</label>
                        <textarea id="problem_statement" name="problem_statement" rows="4" class="sgp-input" required placeholder="Explique o problema central e seus impactos.">{{ old('problem_statement', $project->problem_statement) }}</textarea>
                    </div>
                    <div>
                        <label for="solution_summary" class="sgp-field-label">Solução proposta *</label>
                        <textarea id="solution_summary" name="solution_summary" rows="4" class="sgp-input" required placeholder="Apresente, em alto nível, como a solução responderá ao problema.">{{ old('solution_summary', $project->solution_summary) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-[#DCE3E7] bg-white p-6 shadow-sm">
                <h2 class="font-bold text-[#24313A]">Público, escopo e condições</h2>
                <p class="mt-1 text-sm text-[#667680]">Nos campos em lista, informe um item por linha. O documento fará a formatação automaticamente.</p>
                <div class="mt-5 grid gap-6 lg:grid-cols-2">
                    <div>
                        <label for="target_audience" class="sgp-field-label">Público-alvo e partes interessadas *</label>
                        <textarea id="target_audience" name="target_audience" rows="6" class="sgp-input" required placeholder="Analistas de requisitos&#10;Gestores da unidade&#10;Usuários finais">{{ old('target_audience', $project->target_audience) }}</textarea>
                    </div>
                    <div>
                        <label for="scope_included" class="sgp-field-label">Escopo incluído *</label>
                        <textarea id="scope_included" name="scope_included" rows="6" class="sgp-input" required placeholder="Cadastro de projetos&#10;Gestão de requisitos&#10;Geração de documentos">{{ old('scope_included', $project->scope_included) }}</textarea>
                    </div>
                    <div>
                        <label for="scope_excluded" class="sgp-field-label">Fora do escopo</label>
                        <textarea id="scope_excluded" name="scope_excluded" rows="5" class="sgp-input" placeholder="Integrações previstas apenas para versões futuras">{{ old('scope_excluded', $project->scope_excluded) }}</textarea>
                    </div>
                    <div>
                        <label for="assumptions" class="sgp-field-label">Premissas</label>
                        <textarea id="assumptions" name="assumptions" rows="5" class="sgp-input" placeholder="Disponibilidade da equipe para validações">{{ old('assumptions', $project->assumptions) }}</textarea>
                    </div>
                    <div>
                        <label for="constraints" class="sgp-field-label">Restrições</label>
                        <textarea id="constraints" name="constraints" rows="5" class="sgp-input" placeholder="Prazo institucional&#10;Tecnologias homologadas">{{ old('constraints', $project->constraints) }}</textarea>
                    </div>
                    <div>
                        <label for="success_criteria" class="sgp-field-label">Critérios de sucesso</label>
                        <textarea id="success_criteria" name="success_criteria" rows="5" class="sgp-input" placeholder="Homologação pelos usuários-chave&#10;Redução do retrabalho documental">{{ old('success_criteria', $project->success_criteria) }}</textarea>
                    </div>
                </div>
                <div class="mt-6">
                    <label for="future_vision" class="sgp-field-label">Visão de futuro</label>
                    <textarea id="future_vision" name="future_vision" rows="4" class="sgp-input" placeholder="Evoluções previstas após a primeira versão.">{{ old('future_vision', $project->future_vision) }}</textarea>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('projects.documents.index', $project) }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] bg-white px-5 py-3 text-sm font-semibold text-[#667680] hover:bg-[#F5F7F9]">Cancelar</a>
                <button class="sgp-button-primary w-auto">Salvar informações</button>
            </div>
        </form>
    </div>
</x-app-layout>
