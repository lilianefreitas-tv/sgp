# Módulo de Modelos e Geração de Documentos

## Escopo

O módulo implementa os requisitos `RF044` a `RF049` do MVP 1.0 e as regras
`RN042` a `RN044`.

| Identificador | Implementação |
|---|---|
| RF044 | Geração do Documento de Visão com dados do projeto, cliente, equipe e informações complementares |
| RF045 | Geração da Lista de Requisitos com versão, prioridade, situação, responsável e critérios de aceite |
| RF046 | Geração da Lista de Tarefas com vínculos, responsável, estimativa, prazo e situação |
| RF047 | Armazenamento privado dos arquivos e histórico por projeto |
| RF048 | Exportação e download em PDF |
| RF049 | Exportação e download em DOCX |
| RN042 | Geração automática a partir dos registros do SGP |
| RN043 | Modelos versionados e personalizáveis dentro dos tipos suportados pelo módulo |
| RN044 | Nova versão criada a cada geração, sem sobrescrever documentos anteriores |

### Ampliação controlada do MVP

`EV-DOC-001` inclui o **Backlog Consolidado do Projeto** como quarto tipo de
artefato, sem renumerar os requisitos existentes. O relatório apresenta os
dados essenciais do projeto, agrupa as tarefas por requisito e mantém uma seção
própria para tarefas sem requisito vinculado.

## Decisões

- Uma geração produz DOCX e PDF na mesma versão.
- Os quatro tipos disponíveis são Documento de Visão, Lista de Requisitos,
  Lista de Tarefas e Backlog Consolidado do Projeto.
- Os arquivos ficam no disco privado configurado como `local`.
- O Documento de Visão exige contexto, problema, solução, público-alvo e escopo.
- Informações complementares ficam armazenadas no projeto e podem ser revistas.
- Administradores gerenciam modelos.
- Administradores, Gerentes de Projetos e Analistas de Requisitos geram documentos.
- Qualquer participante ativo do projeto pode consultar e baixar documentos.
- O MVP permite personalizar nome, versão, descrição, cabeçalho e rodapé.
- A estrutura de conteúdo continua controlada pelo tipo do artefato.
- O rodapé preserva o texto configurado no modelo e acrescenta usuário, data,
  hora e paginação como trilha de auditoria.
- A capa utiliza somente a área útil da página, evitando a criação de uma folha
  inicial vazia no PDF.

## Dependências

- `phpoffice/phpword` para DOCX.
- `dompdf/dompdf` para PDF.
- Extensões PHP `dom`, `gd`, `xmlwriter` e `zip` habilitadas.

## Armazenamento

Os arquivos são gravados em:

`storage/app/private/generated-documents/{projeto}/{tipo}/`

Não criar link público para essa pasta. O acesso ocorre por rota autenticada e
com verificação de participação no projeto.

## Testes acrescentados

Os cenários automatizados cobrem modelos iniciais, visibilidade, permissões,
preenchimento do Documento de Visão, geração dos dois formatos, versionamento,
download, isolamento entre projetos, Backlog Consolidado e rodapé de auditoria.
