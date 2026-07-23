# Módulo de Gestão de Tarefas

## Escopo implementado

O módulo cobre os requisitos funcionais `RF031` a `RF039`, os casos de uso
`UC007` e `UC008` e as regras de negócio `RN021` a `RN025`.

| Requisito | Implementação principal | Validação |
| --- | --- | --- |
| RF031, RF032 e RF033 | Cadastro, edição, detalhe, visão por projeto e visão geral | `TaskManagementTest` |
| RF034 | `tasks.project_id` obrigatório | Migration e validação de rota |
| RF035 | `tasks.requirement_id` opcional e restrito ao mesmo projeto | Form Request e teste de integridade |
| RF036 | `tasks.responsible_id` opcional e restrito à equipe ativa | Form Request e teste de integridade |
| RF037 | `start_date` e `due_date`, com prazo igual ou posterior ao início | Form Request |
| RF038 | Seis status e histórico das mudanças | `TaskStatus`, `task_histories` e testes |
| RF039 | Quatro prioridades | `TaskPriority` |
| RN025 | `parent_task_id` opcional, limitado a uma tarefa principal do mesmo projeto | Form Request e teste de hierarquia |

## Correspondência conceitual e técnica

| Documentação conceitual | Laravel/PostgreSQL |
| --- | --- |
| tarefas | `tasks` |
| id_projeto | `project_id` |
| id_requisito | `requirement_id` |
| id_responsavel | `responsible_id` |
| id_tarefa_pai | `parent_task_id` |
| estimativa_horas | `estimated_hours` (entrada e exibição em `HH:MM`; armazenamento interno em horas decimais) |
| data_inicio | `start_date` |
| data_prazo | `due_date` |
| data_conclusao | `completed_at` |
| ativo | `is_active` |

O código é gerado no padrão `TAR-001`, sequencial por projeto. O campo não é
preenchível pelo formulário.

## Permissões

Administradores, Gerentes de Projetos, Analistas de Requisitos e
Desenvolvedores podem gerenciar tarefas. Os demais participantes ativos podem
consultá-las. Usuários comuns enxergam na visão geral apenas tarefas dos
projetos dos quais participam.

## Limites desta entrega

O Kanban usará os mesmos status e será implementado no módulo seguinte.
Colaboradores auxiliares, sprints, comentários, anexos e geração da Lista de
Tarefas permanecem nas etapas posteriores do MVP.
