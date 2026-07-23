<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $project->code }}</title>
    <style>
        @page { margin: 70px 52px 58px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #24313A; font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.45; }
        header { position: fixed; top: -48px; left: 0; right: 0; height: 34px; border-bottom: 1px solid #DCE3E7; color: #667680; font-size: 8pt; }
        header table { width: 100%; border: 0; }
        header td { padding: 0; border: 0; vertical-align: middle; }
        footer { position: fixed; bottom: -43px; left: 0; right: 0; border-top: 1px solid #DCE3E7; padding-top: 5px; color: #667680; text-align: center; font-size: 7.5pt; line-height: 1.25; }
        footer .page-number:after { content: counter(page); }
        h1 { margin: 20px 0 8px; color: #123B4A; font-size: 16pt; page-break-after: avoid; }
        h2 { margin: 16px 0 7px; color: #287EA1; font-size: 12pt; page-break-after: avoid; }
        p { margin: 0 0 9px; text-align: justify; }
        ul { margin: 4px 0 10px 20px; padding: 0; }
        li { margin-bottom: 4px; }
        table.data { width: 100%; margin: 7px 0 15px; border-collapse: collapse; page-break-inside: avoid; }
        table.data th, table.data td { border: 1px solid #DCE3E7; padding: 7px 8px; vertical-align: middle; }
        table.data th { background: #123B4A; color: #FFF; font-size: 9pt; text-align: left; }
        table.data td.label { width: 28%; background: #F3F7F8; font-weight: bold; }
        .cover { min-height: 610px; padding-top: 145px; text-align: center; page-break-after: always; }
        .cover .brand { color: #287EA1; font-size: 15pt; font-weight: bold; letter-spacing: 1px; }
        .cover h1 { margin-top: 28px; font-size: 26pt; text-align: center; }
        .cover .project-name { margin-top: 20px; font-size: 17pt; text-align: center; }
        .cover .code { color: #667680; font-weight: bold; text-align: center; }
        .cover .version { margin-top: 115px; text-align: center; }
        .item { page-break-inside: avoid; margin-bottom: 18px; }
        .muted { color: #667680; }
        table.backlog { page-break-inside: auto; font-size: 8.2pt; }
        table.backlog th, table.backlog td { padding: 5px 6px; }
        table.backlog tr { page-break-inside: avoid; }
        .requirement-meta { margin: -2px 0 7px; color: #667680; font-size: 8.5pt; text-align: left; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td style="width: 20%; color: #123B4A; font-size: 11pt; font-weight: bold;">SGP</td>
                <td style="text-align: right;">{{ $template->header_text ?: 'Sistema de Gestão de Projetos de Software' }}</td>
            </tr>
        </table>
    </header>
    <footer>
        <div>{{ $template->footer_text ?: 'Documento gerado automaticamente pelo SGP' }}</div>
        <div>Gerado por: {{ $generatedBy->name }} | {{ $generatedAt->format('d/m/Y H:i') }} | Página <span class="page-number"></span></div>
    </footer>

    <section class="cover">
        <div class="brand">SGP</div>
        <h1>{{ $title }}</h1>
        <p class="project-name">{{ $project->name }}</p>
        <p class="code">{{ $project->code }}</p>
        <p class="version">Versão {{ $version }}.0<br>{{ $generatedAt->format('d/m/Y') }}</p>
    </section>

    @if ($type === \App\Enums\DocumentType::Vision)
        <h1>1. Identificação do projeto</h1>
        <table class="data">
            <tr><td class="label">Projeto</td><td>{{ $project->name }}</td></tr>
            <tr><td class="label">Código</td><td>{{ $project->code }}</td></tr>
            <tr><td class="label">Cliente ou unidade</td><td>{{ $project->client->name }}</td></tr>
            <tr><td class="label">Responsável</td><td>{{ $project->manager->name }}</td></tr>
            <tr><td class="label">Nível de gestão</td><td>{{ $project->management_level->label() }}</td></tr>
            <tr><td class="label">Metodologia</td><td>{{ $project->methodology ?: 'Não informada' }}</td></tr>
            <tr><td class="label">Situação</td><td>{{ $project->status->label() }}</td></tr>
            <tr><td class="label">Período previsto</td><td>{{ $project->start_date?->format('d/m/Y') ?? 'não informado' }} a {{ $project->expected_end_date?->format('d/m/Y') ?? 'não informado' }}</td></tr>
        </table>

        <h1>2. Introdução</h1>
        <h2>2.1 Finalidade</h2>
        <p>Este documento apresenta a visão geral do projeto, seus objetivos, contexto, problema, solução proposta, público-alvo e limites de escopo. As informações foram consolidadas a partir dos registros mantidos no SGP.</p>
        <h2>2.2 Contexto</h2>
        <p>{!! nl2br(e($project->document_context)) !!}</p>
        <h1>3. Problema</h1>
        <p>{!! nl2br(e($project->problem_statement)) !!}</p>
        <h1>4. Solução proposta</h1>
        <p>{!! nl2br(e($project->solution_summary)) !!}</p>
        <h1>5. Objetivo geral</h1>
        <p>{!! nl2br(e($project->objective)) !!}</p>

        @php
            $listSections = [
                '6. Público-alvo e partes interessadas' => $project->target_audience,
                '7. Escopo incluído' => $project->scope_included,
                '8. Fora do escopo' => $project->scope_excluded,
                '9. Premissas' => $project->assumptions,
                '10. Restrições' => $project->constraints,
                '11. Critérios de sucesso' => $project->success_criteria,
            ];
        @endphp
        @foreach ($listSections as $sectionTitle => $sectionText)
            <h1>{{ $sectionTitle }}</h1>
            @php
                $sectionItems = collect(preg_split('/\R/u', (string) $sectionText))
                    ->map(fn ($item) => trim(preg_replace('/^[\s\-•]+/u', '', $item)))
                    ->filter();
            @endphp
            @if ($sectionItems->isEmpty())
                <p class="muted">Nenhum item informado.</p>
            @else
                <ul>
                    @foreach ($sectionItems as $sectionItem)
                        <li>{{ $sectionItem }}</li>
                    @endforeach
                </ul>
            @endif
        @endforeach

        <h1>12. Equipe do projeto</h1>
        <table class="data">
            <thead><tr><th>Participante</th><th>E-mail</th><th>Papel no projeto</th></tr></thead>
            <tbody>
                @foreach ($members as $member)
                    <tr><td>{{ $member['user']->name }}</td><td>{{ $member['user']->email }}</td><td>{{ $member['roles'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <h1>13. Visão consolidada do escopo</h1>
        <p>Requisitos cadastrados: {{ $requirements->count() }}. Tarefas cadastradas: {{ $tasks->count() }}.</p>
        <h1>14. Visão de futuro</h1>
        <p>{!! nl2br(e($project->future_vision ?: 'Não informada.')) !!}</p>
    @elseif ($type === \App\Enums\DocumentType::RequirementsList)
        <h1>1. Identificação</h1>
        <table class="data">
            <tr><td class="label">Projeto</td><td>{{ $project->name }}</td></tr>
            <tr><td class="label">Código</td><td>{{ $project->code }}</td></tr>
            <tr><td class="label">Responsável</td><td>{{ $project->manager->name }}</td></tr>
            <tr><td class="label">Total de requisitos</td><td>{{ $requirements->count() }}</td></tr>
        </table>
        <h1>2. Requisitos do projeto</h1>
        @if ($requirements->isEmpty())
            <p>Nenhum requisito cadastrado no projeto.</p>
        @else
        @foreach ($requirements as $requirement)
            <section class="item">
                <h2>{{ $requirement->code }} - {{ $requirement->title }}</h2>
                <table class="data">
                    <tr><td class="label">Tipo</td><td>{{ $requirement->type->label() }}</td></tr>
                    <tr><td class="label">Prioridade</td><td>{{ $requirement->priority->label() }}</td></tr>
                    <tr><td class="label">Situação</td><td>{{ $requirement->status->label() }}</td></tr>
                    <tr><td class="label">Responsável</td><td>{{ $requirement->responsible?->name ?: 'Não definido' }}</td></tr>
                    <tr><td class="label">Origem</td><td>{{ $requirement->source ?: 'Não informada' }}</td></tr>
                    <tr><td class="label">Versão</td><td>{{ $requirement->current_version }}</td></tr>
                </table>
                <p><strong>Descrição:</strong><br>{!! nl2br(e($requirement->description)) !!}</p>
                <p><strong>Critérios de aceite:</strong><br>{!! nl2br(e($requirement->acceptance_criteria ?: 'Não informados.')) !!}</p>
            </section>
        @endforeach
        @endif
    @elseif ($type === \App\Enums\DocumentType::TasksList)
        <h1>1. Identificação</h1>
        <table class="data">
            <tr><td class="label">Projeto</td><td>{{ $project->name }}</td></tr>
            <tr><td class="label">Código</td><td>{{ $project->code }}</td></tr>
            <tr><td class="label">Responsável</td><td>{{ $project->manager->name }}</td></tr>
            <tr><td class="label">Total de tarefas</td><td>{{ $tasks->count() }}</td></tr>
        </table>
        <h1>2. Tarefas do projeto</h1>
        @if ($tasks->isEmpty())
            <p>Nenhuma tarefa cadastrada no projeto.</p>
        @else
        @foreach ($tasks as $task)
            <section class="item">
                <h2>{{ $task->code }} - {{ $task->title }}</h2>
                <table class="data">
                    <tr><td class="label">Situação</td><td>{{ $task->status->label() }}</td></tr>
                    <tr><td class="label">Prioridade</td><td>{{ $task->priority->label() }}</td></tr>
                    <tr><td class="label">Responsável</td><td>{{ $task->responsible?->name ?: 'Não definido' }}</td></tr>
                    <tr><td class="label">Requisito</td><td>{{ $task->requirement ? $task->requirement->code.' - '.$task->requirement->title : 'Não vinculado' }}</td></tr>
                    <tr><td class="label">Tarefa principal</td><td>{{ $task->parent ? $task->parent->code.' - '.$task->parent->title : 'Não se aplica' }}</td></tr>
                    <tr><td class="label">Estimativa</td><td>{{ $task->estimatedDuration() ?: 'Não informada' }}</td></tr>
                    <tr><td class="label">Prazo</td><td>{{ $task->due_date?->format('d/m/Y') ?: 'Não informado' }}</td></tr>
                </table>
                <p><strong>Descrição:</strong><br>{!! nl2br(e($task->description ?: 'Não informada.')) !!}</p>
            </section>
        @endforeach
        @endif
    @else
        <h1>1. Identificação</h1>
        <table class="data">
            <tr><td class="label">Projeto</td><td>{{ $project->name }}</td></tr>
            <tr><td class="label">Código</td><td>{{ $project->code }}</td></tr>
            <tr><td class="label">Responsável</td><td>{{ $project->manager->name }}</td></tr>
            <tr><td class="label">Situação</td><td>{{ $project->status->label() }}</td></tr>
            <tr><td class="label">Requisitos</td><td>{{ $requirements->count() }}</td></tr>
            <tr><td class="label">Tarefas</td><td>{{ $tasks->count() }}</td></tr>
        </table>

        <h1>2. Backlog por requisito</h1>
        @if ($requirements->isEmpty())
            <p>Nenhum requisito cadastrado no projeto.</p>
        @else
            @foreach ($requirements as $requirement)
                <h2>{{ $requirement->code }} - {{ $requirement->title }}</h2>
                <p class="requirement-meta">
                    Tipo: {{ $requirement->type->label() }} |
                    Prioridade: {{ $requirement->priority->label() }} |
                    Situação: {{ $requirement->status->label() }}
                </p>
                @php
                    $requirementTasks = $tasks->where('requirement_id', $requirement->id);
                @endphp
                @if ($requirementTasks->isEmpty())
                    <p class="muted">Nenhuma tarefa vinculada a este requisito.</p>
                @else
                    <table class="data backlog">
                        <thead>
                            <tr>
                                <th style="width: 32%;">Tarefa</th>
                                <th style="width: 13%;">Situação</th>
                                <th style="width: 11%;">Prioridade</th>
                                <th style="width: 18%;">Responsável</th>
                                <th style="width: 12%;">Estimativa</th>
                                <th style="width: 14%;">Prazo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requirementTasks as $task)
                                <tr>
                                    <td>{{ $task->code }} - {{ $task->title }}</td>
                                    <td>{{ $task->status->label() }}</td>
                                    <td>{{ $task->priority->label() }}</td>
                                    <td>{{ $task->responsible?->name ?: 'Não definido' }}</td>
                                    <td>{{ $task->estimatedDuration() ?: 'N/I' }}</td>
                                    <td>{{ $task->due_date?->format('d/m/Y') ?: 'N/I' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif

        <h1>3. Tarefas sem requisito vinculado</h1>
        @php
            $unlinkedTasks = $tasks->whereNull('requirement_id');
        @endphp
        @if ($unlinkedTasks->isEmpty())
            <p class="muted">Nenhuma tarefa sem requisito vinculado.</p>
        @else
            <table class="data backlog">
                <thead>
                    <tr>
                        <th style="width: 32%;">Tarefa</th>
                        <th style="width: 13%;">Situação</th>
                        <th style="width: 11%;">Prioridade</th>
                        <th style="width: 18%;">Responsável</th>
                        <th style="width: 12%;">Estimativa</th>
                        <th style="width: 14%;">Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unlinkedTasks as $task)
                        <tr>
                            <td>{{ $task->code }} - {{ $task->title }}</td>
                            <td>{{ $task->status->label() }}</td>
                            <td>{{ $task->priority->label() }}</td>
                            <td>{{ $task->responsible?->name ?: 'Não definido' }}</td>
                            <td>{{ $task->estimatedDuration() ?: 'N/I' }}</td>
                            <td>{{ $task->due_date?->format('d/m/Y') ?: 'N/I' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</body>
</html>
