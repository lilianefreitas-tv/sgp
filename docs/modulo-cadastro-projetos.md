# Cadastro de Projetos

## Escopo implementado

Este pacote implementa a Fase 2 dos cadastros principais do MVP 1.0:

- clientes, órgãos, setores e unidades demandantes;
- projetos;
- responsável principal;
- equipe e papéis exercidos no projeto;
- consulta, filtros, edição, inativação e arquivamento lógico;
- indicadores e projetos recentes no painel inicial.

## Rastreabilidade

| Implementação | Requisitos e regras atendidos |
|---|---|
| Clientes e unidades | RF008 a RF011, UC002 |
| Cadastro e consulta de projetos | RF012 a RF014, UC003 |
| Encerramento, status e nível de gestão | RF015 a RF018, RN001 a RN004, RN006 a RN008 |
| Equipe e múltiplos papéis contextuais | RF019 a RF022, UC004, RN009 a RN011 |
| Exclusão lógica e preservação histórica | RN005, RN007 e RN008 |

## Correspondência entre documentação e banco Laravel

Os documentos conceituais permanecem em português. A implementação segue as
convenções técnicas do Laravel em inglês.

| Conceito documental | Implementação técnica |
|---|---|
| clientes | `clients` |
| projetos | `projects` |
| projeto_usuarios | `project_user` |
| id_cliente | `client_id` |
| id_responsavel | `manager_id` |
| nivel_gestao | `management_level` |
| papel_projeto | `role` |
| ativo | `is_active` |
| criado_em e atualizado_em | `created_at` e `updated_at` |

## Decisões aplicadas

- Perfil global e papel no projeto são estruturas independentes.
- Um usuário pode exercer vários papéis no mesmo projeto.
- O responsável principal recebe automaticamente o papel de Gerente de Projetos.
- O responsável principal não pode ser removido da equipe sem que outro seja definido.
- Projetos não possuem exclusão física pela interface.
- Status, atividade, conclusão, cancelamento e arquivamento são tratados separadamente.
- O código do projeto é gerado automaticamente no padrão `PRJ-0001`.
- Clientes e usuários inativos permanecem vinculados aos projetos antigos, mas não
  podem ser selecionados em novos projetos.
- O histórico dos papéis é preservado por ativação, inativação e datas do vínculo.
