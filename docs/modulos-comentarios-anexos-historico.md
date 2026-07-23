# Comentários, Anexos e Histórico Consolidado

## 1. Objetivo

Completar o núcleo colaborativo e de auditoria do MVP 1.0, permitindo registrar comunicações contextuais, armazenar arquivos de forma controlada e consultar os principais eventos do projeto em uma linha do tempo única.

## 2. Escopo implementado

### Comentários

- Registro de comentário no projeto, em requisito ou em tarefa.
- Consulta cronológica por projeto.
- Identificação do autor, data, hora e registro relacionado.
- Limite de 5.000 caracteres.
- Acesso restrito a participantes ativos e administradores.

### Anexos

- Upload vinculado ao projeto, requisito ou tarefa.
- Armazenamento no disco privado do Laravel.
- Registro de nome original, tipo, extensão, tamanho, autor, data e descrição.
- Download autorizado somente para participantes ativos e administradores.
- Limite de tamanho e extensões configuráveis em `config/sgp.php`.
- Remoção permitida ao autor do upload, gerente do projeto ou administrador.
- Remoção lógica, sem apagar o arquivo físico, preservando a evidência de auditoria.

### Histórico consolidado

- Linha do tempo do projeto em ordem cronológica decrescente.
- Filtros por projeto/equipe, requisitos, tarefas/Kanban, documentos, comentários e anexos.
- Consolidação de:
  - cadastro e alterações gerais do projeto;
  - atualizações da equipe;
  - versões de requisitos;
  - histórico de tarefas e movimentações do Kanban;
  - documentos gerados;
  - comentários registrados;
  - anexos enviados e removidos.
- Identificação do responsável, data e hora de cada evento.

## 3. Permissões

| Ação | Participante ativo | Autor do anexo | Gerente | Administrador |
| --- | ---: | ---: | ---: | ---: |
| Consultar comentários, anexos e histórico | Sim | Sim | Sim | Sim |
| Registrar comentário | Sim | Sim | Sim | Sim |
| Enviar e baixar anexo | Sim | Sim | Sim | Sim |
| Remover anexo de outro usuário | Não | Não se não for o autor | Sim | Sim |

Usuários sem participação ativa não podem acessar os módulos do projeto.

## 4. Rastreabilidade

| Caso de uso | Requisitos cobertos | Evidência principal |
| --- | --- | --- |
| UC013 – Inserir Comentário | RF050, RF051 | `ProjectCommentController` e testes de comentário contextual |
| UC014 – Anexar Arquivo | RF052, RF053, RF054 | `ProjectAttachmentController` e testes de upload, download e remoção |
| UC015 – Visualizar Histórico | RF055, RF056 | `ProjectHistoryService`, filtros e testes de consolidação |
| Auditoria e arquivos | RN005, RNF029 a RNF035 | armazenamento privado, autoria, data/hora e linha do tempo |

## 5. Configuração

Valores-padrão:

```text
Tamanho máximo: 10 MB
Extensões: pdf, doc, docx, xls, xlsx, csv, txt, png, jpg, jpeg, webp, zip
```

Configuração opcional no `.env`:

```dotenv
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

## 6. Decisões de segurança

- Arquivos não são expostos por URL pública.
- O nome fornecido pelo usuário não é utilizado como caminho físico.
- Tipos executáveis e formatos ativos não fazem parte da lista-padrão.
- O vínculo do registro é validado contra o projeto acessado.
- Anexos removidos deixam de ser consultáveis e baixáveis, mas permanecem preservados para auditoria.
- Conteúdo exibido nas telas é escapado pelo Blade.

## 7. Testes automatizados

Os cenários cobrem:

- comentário por participante ativo;
- bloqueio de usuário externo;
- rejeição de vínculo pertencente a outro projeto;
- upload e download privados;
- rejeição de extensão não permitida;
- regras de remoção;
- bloqueio de download entre projetos;
- consolidação das fontes do histórico;
- filtro do histórico por categoria.
