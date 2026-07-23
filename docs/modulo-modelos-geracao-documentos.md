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
| RN043 | Modelos versionados e personalizáveis dentro dos três tipos suportados no MVP |
| RN044 | Nova versão criada a cada geração, sem sobrescrever documentos anteriores |

## Decisões

- Uma geração produz DOCX e PDF na mesma versão.
- Os arquivos ficam no disco privado configurado como `local`.
- O Documento de Visão exige contexto, problema, solução, público-alvo e escopo.
- Informações complementares ficam armazenadas no projeto e podem ser revistas.
- Administradores gerenciam modelos.
- Administradores, Gerentes de Projetos e Analistas de Requisitos geram documentos.
- Qualquer participante ativo do projeto pode consultar e baixar documentos.
- O MVP permite personalizar nome, versão, descrição, cabeçalho e rodapé.
- A estrutura de conteúdo continua controlada pelo tipo do artefato.

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
download e isolamento entre projetos.
