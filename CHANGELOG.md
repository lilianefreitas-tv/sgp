# Changelog

## [3.0.0] - 2026-08-30

Release da `BL-SGP-003 · Engenharia Documental Adaptativa`, homologada funcionalmente e preparada para promoção controlada à `main`.

### Adicionado

- entrada por iniciativa, jornada comercial opcional e conversão rastreável em projeto;
- aplicabilidade e governança proporcionais ao contexto da iniciativa e do projeto;
- artefatos estruturados, revisões, aprovação e publicações documentais;
- contratos, baselines de projeto, itens congelados e comparação controlada;
- solicitações de mudança, análises de impacto e registro da implementação;
- casos de teste, execuções, evidências e matriz de rastreabilidade;
- comunicação transacional por SMTP com diagnóstico seguro e execução em fila;
- recuperação pública de senha, reenvio administrativo e senha temporária com troca obrigatória;
- auditoria global de segurança da plataforma separada da auditoria organizacional.

### Segurança

- segredos de SMTP permanecem exclusivamente nas variáveis protegidas do ambiente;
- tokens de redefinição são temporários, de uso único e sujeitos a limitação;
- redefinições administrativas revogam sessões e invalidam links anteriores;
- eventos globais registram ator, alvo, ambiente e resultado sem armazenar credenciais;
- permissões e isolamento multiempresa foram mantidos nos novos módulos.

### Validação

- 367 testes automatizados aprovados, com 1.473 asserções;
- `git diff --check` sem inconsistências;
- percursos P01 a P09 homologados pela responsável do produto;
- envio SMTP real, redefinição pública, senha temporária e auditorias validados funcionalmente.

### Publicação

- a tag `v3.0.0` deve ser criada somente no commit realmente implantado na `main`;
- o commit e as evidências do Laravel Cloud serão registrados no selo final do P10;
- a identidade visual vigente é preservada nesta release; sua reformulação fica para análise da `BL-SGP-004`.

## [2.0.1-rc2] - 2026-08-07

### Adicionado

- recuperação pública de senha com resposta neutra e limitação de solicitações;
- reenvio administrativo seguro para Superadmin, Proprietário e Administrador da organização;
- auditoria específica dos eventos de recuperação de acesso;
- testes de permissões, isolamento, conta inativa e ausência de exposição de tokens.

### Alterado

- primeiro acesso passa a ser enviado por e-mail, sem exibir o link na interface;
- identificação da versão passa a usar `SGP_RELEASE_LABEL`;
- redefinição de senha passa a aceitar somente contas ativas.

## [2.0.1-rc1] - 2026-08-04

Correção operacional candidata para a primeira implantação da Fundação SaaS no
banco legado de produção, sem alteração da baseline funcional `BL-SGP-002`.

### Adicionado

- migrations seletivas e transacionais para distribuir o legado antes das
  restrições e normalizar os códigos dos modelos depois da unicidade organizacional;
- validação estrita do inventário aprovado de produção;
- criação controlada das organizações `mppa` e `sgp` e dos vínculos da
  Administradora principal;
- distribuição dos quatro projetos e dos registros dependentes nas 16 tabelas de
  domínio;
- duplicação dos modelos documentais e reconciliação das referências dos
  documentos do projeto SGP;
- verificador pós-migração `sgp:verify-production-transition`;
- importador seletivo `sgp:import-selective-project-data`, com simulação padrão,
  validação de manifesto SHA-256 e gravação transacional somente com `--apply`;
- testes da transição real, da interrupção por divergência, da instalação limpa e
  da importação seletiva em um banco novo;
- CI no GitHub Actions para compilar os ativos e executar a suíte Laravel em cada PR.
- ensaio adicional em PostgreSQL 18 descartável, com travas contra uso acidental
  de banco externo e geração de evidências técnicas com manifesto SHA-256.

### Segurança operacional

- a migration bloqueia gravações concorrentes nas tabelas envolvidas no
  PostgreSQL enquanto a transação está aberta;
- qualquer diferença de contagem, identidade ou relacionamento interrompe a
  implantação;
- a reversão depois da migração depende de restauração ponto a ponto do banco;
- a tag histórica `v2.0.0` e o commit homologado `1ab7302` permanecem inalterados.

## [1.0.1] - 2026-07-28

Correção operacional do MVP para implantação em plataformas com filesystem
efêmero, sem ampliação do escopo funcional da `BL-SGP-001`.

### Adicionado

- dependência `league/flysystem-aws-s3-v3` para Object Storage compatível com
  S3;
- configuração `SGP_PRIVATE_DISK`, com herança de `FILESYSTEM_DISK`;
- geração temporária de DOCX e PDF antes do envio ao disco persistente;
- bootstrap não interativo do primeiro administrador por variável protegida;
- testes de anexos e documentos usando disco privado configurado.

### Corrigido

- removidos os usos fixos do disco `local` nos fluxos permanentes de anexos e
  documentos;
- anexos novos registram o disco efetivamente utilizado, preservando a leitura
  dos registros anteriores;
- downloads continuam passando pelas rotas autenticadas e autorizações do SGP;
- falhas de geração removem objetos parciais e arquivos temporários;
- criação do primeiro administrador passa a funcionar no console não
  interativo do Laravel Cloud;
- README e manual de instalação atualizados para ambiente local e nuvem.

### Validação

- 100 testes automatizados aprovados, com 358 asserções;
- compatibilidade regressiva do disco local preservada;
- nenhuma migração de banco necessária;
- `HOM-035` permanece pendente para validação no ambiente real após novo deploy.

## [1.0.0] - 2026-07-28

Primeira release estável e homologada do SGP.

### Adicionado

- identidade visual oficial, página inicial e favicon do SGP;
- autenticação e administração de usuários;
- clientes, projetos, equipes e papéis contextuais;
- requisitos versionados e tarefas com histórico;
- Kanban, calendário e Gantt básico;
- geração versionada de DOCX e PDF;
- Backlog Consolidado do Projeto;
- comentários, anexos privados e histórico consolidado;
- dashboard com indicadores reais;
- roteiro integrado de homologação final.

### Corrigido

- removida a exclusão física de conta pelo próprio usuário;
- `.env.example` alinhado ao SGP e ao PostgreSQL;
- removida a credencial previsível do seeder e criado procedimento interativo
  para o primeiro administrador;
- sessões autenticadas passam a ser encerradas imediatamente quando a conta é
  desativada;
- licença do pacote corrigida para identificar o SGP como software
  proprietário, removendo a declaração MIT herdada do esqueleto Laravel;
- README, tecnologias, status e instruções de implantação atualizados;
- referências herdadas do Laravel e do RotaMP removidas da experiência pública;
- artefatos temporários excluídos do pacote de release.

### Conhecido

- recuperação pública de senha não integra o MVP;
- 95 testes automatizados, com 337 asserções, usam SQLite em memória;
- `HOM-001` a `HOM-034` foram aprovados em PostgreSQL;
- `HOM-035` depende da implantação externa para validar HTTPS, webroot e
  permissões do servidor;
- a compatibilidade com Object Storage está prevista para `v1.0.1`, antes do
  primeiro deploy em plataforma com sistema de arquivos efêmero;
- a Fundação SaaS Multiempresa e a Configuração Adaptativa de Projetos estão
  aprovadas na `BL-SGP-002`, mas ainda não foram implementadas;
- Wiki, Sprints, reuniões, gestão interna de testes, contratos, baselines
  operacionais, mudanças, IA e Gantt avançado permanecem como evolução futura.
