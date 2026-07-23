# Módulo Kanban

## Escopo implementado

O módulo implementa o quadro Kanban por projeto sobre a base do Módulo de
Gestão de Tarefas. Somente tarefas ativas são exibidas. Cada movimentação
altera o status da tarefa e registra o evento no histórico.

## Rastreabilidade

| Identificador | Implementação | Verificação |
| --- | --- | --- |
| RF040 | Visão geral de quadros e quadro individual por projeto | `KanbanManagementTest::test_opening_board_creates_six_default_columns_and_groups_tasks` |
| RF041 | Tarefas agrupadas pelas seis etapas do fluxo | Teste de criação e agrupamento do quadro |
| RF042 | Arrastar e soltar e seletor alternativo de movimentação | Testes de movimentação, permissão, histórico e integridade entre projetos |
| RF043 | Configuração de nome e ordem das colunas | Teste de configuração por Gerente de Projetos |
| UC009 | Acesso pela aba Kanban dentro do projeto e pelo menu lateral | Testes de visualização e visibilidade por participação |

## Estrutura técnica

A documentação conceitual em português corresponde às seguintes tabelas
Laravel:

| Conceito | Tabela técnica |
| --- | --- |
| Quadro Kanban | `kanban_boards` |
| Coluna Kanban | `kanban_columns` |
| Posição da tarefa | `kanban_task_positions` |
| Histórico da movimentação | `task_histories` com evento `kanban_moved` |

As colunas possuem um status técnico fixo para manter compatibilidade com as
tarefas. Gerentes de Projetos e Administradores podem alterar o nome exibido e
a ordem das colunas sem quebrar o fluxo.

## Permissões

- Administrador: visualiza, movimenta e configura todos os quadros.
- Gerente de Projetos: visualiza, movimenta e configura quadros dos projetos
  em que exerce esse papel.
- Analista de Requisitos e Desenvolvedor: visualizam e movimentam tarefas.
- Outros participantes ativos: acesso somente para consulta.

## Regras aplicadas

- Tarefas inativas não aparecem e não podem ser movimentadas no quadro.
- Uma tarefa não pode ser movimentada pelo quadro de outro projeto.
- Ao entrar em `Concluído`, a data de conclusão é preenchida automaticamente.
- Ao sair de `Concluído`, a data de conclusão é removida.
- O quadro registra origem, destino, usuário e momento da movimentação.
- Filtros não alteram nem removem dados, apenas limitam a visualização.
