# Dashboard, Calendário e Cronograma Básico

## Escopo da entrega

Esta ampliação conclui os indicadores básicos previstos para o painel do MVP e acrescenta duas visualizações gerenciais que utilizam exclusivamente os dados já existentes.

### Dashboard

- oito indicadores com dados reais;
- situação dos projetos;
- tarefas por etapa;
- progresso calculado por tarefas concluídas;
- próximos prazos;
- projetos que exigem atenção;
- atividades recentes do histórico consolidado.

O progresso do projeto é calculado pela fórmula:

`tarefas ativas concluídas ÷ total de tarefas ativas × 100`

Um projeto é considerado atrasado quando a data prevista de término já venceu e sua situação não é `Concluído` nem `Cancelado`. Uma tarefa é considerada atrasada quando seu prazo venceu e ela ainda não está concluída.

### Calendário

O calendário geral e o calendário do projeto apresentam:

- início e entrega prevista dos projetos;
- início e prazo das tarefas;
- conclusão das tarefas;
- itens sem planejamento.

Filtros disponíveis: mês, projeto, responsável, situação da tarefa e tipo de evento. Todos os resultados respeitam a visibilidade do usuário.

### Cronograma Gantt básico

O Gantt fica disponível em cada projeto e apresenta tarefas agrupadas por requisito, período planejado, responsável, situação e destaque de atraso. Tarefas sem requisito recebem agrupamento próprio, e tarefas sem datas aparecem em uma lista separada.

Não fazem parte desta versão: dependências entre tarefas, caminho crítico, marcos, linha de base, capacidade da equipe e replanejamento automático. Esses recursos permanecem registrados para o Gantt avançado.

## Impacto técnico

- não cria novas tabelas;
- não exige migration;
- não adiciona dependências Composer ou NPM;
- preserva Comentários, Anexos, Histórico e Documentos;
- acrescenta testes de visibilidade, indicadores, filtros do calendário e cronograma.
