# Módulo de Gestão de Requisitos

## Escopo implementado

O módulo implementa os requisitos funcionais `RF023` a `RF030`, os casos de uso `UC005` e `UC006` e as regras de negócio `RN012` a `RN018` aplicáveis ao MVP 1.0.

Foram incluídos:

- cadastro, edição, consulta, pesquisa e filtros de requisitos;
- vínculo obrigatório com o projeto;
- código sequencial automático no padrão `REQ-001`, único dentro de cada projeto;
- título, descrição, tipo, prioridade, status, critérios de aceite e origem;
- responsável opcional, restrito aos participantes ativos do projeto;
- inativação e reativação sem exclusão física;
- versionamento automático das alterações relevantes;
- base física para dependências entre requisitos;
- indicadores de requisitos ativos no painel;
- testes automatizados de regras, permissões e histórico.

## Permissões

- `Administrador`: pode consultar e gerenciar requisitos de todos os projetos.
- `Gerente de Projetos`: pode consultar e gerenciar requisitos dos projetos nos quais exerce esse papel.
- `Analista de Requisitos`: pode consultar e gerenciar requisitos dos projetos nos quais exerce esse papel.
- Demais participantes ativos: podem consultar requisitos do projeto.
- Usuários sem vínculo ativo: não podem acessar os requisitos do projeto.

Os papéis acima são contextuais ao projeto e permanecem separados dos perfis globais `Administrador` e `Usuário`.

## Correspondência entre documentação e implementação

| Conceito documental | Implementação Laravel |
| --- | --- |
| requisitos | `requirements` |
| requisitos_versoes | `requirement_versions` |
| dependências | `requirement_dependencies` |
| id_projeto | `project_id` |
| id_responsavel | `responsible_id` |
| criterio_aceite | `acceptance_criteria` |
| versao_atual | `current_version` |
| ativo | `is_active` |
| criado_em / atualizado_em | `created_at` / `updated_at` |

## Rastreabilidade resumida

| Item | Cobertura |
| --- | --- |
| RF023 | criação vinculada ao projeto |
| RF024 | edição com preservação da versão anterior |
| RF025 | listagem, pesquisa e filtros |
| RF026 | alteração de status |
| RF027 | definição de prioridade |
| RF028 | indicação de responsável da equipe |
| RF029 | registro de critérios de aceite |
| RF030 | código automático por projeto |
| RN012 | chave estrangeira obrigatória para projeto |
| RN013 | restrição única `project_id + code` |
| RN014 | enumeração dos sete status documentados |
| RN015 | tabela e histórico de versões |
| RN016 | tabela de dependências preparada para evolução da interface |
| RN017 | critérios de aceite opcionais |
| RN018 | auxílio de IA mantido como evolução futura, sempre sujeito à confirmação do usuário |

## Limites desta entrega

A interface de dependências entre requisitos e a geração assistida por IA não fazem parte desta etapa. A modelagem de dependências está preparada no banco, e a IA permanece como evolução futura, conforme o caráter opcional definido na documentação.
