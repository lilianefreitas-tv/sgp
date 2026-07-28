# SGP - Sistema de GestÃ£o de Projetos de Software

O SGP centraliza o planejamento, a execuÃ§Ã£o, a documentaÃ§Ã£o e a rastreabilidade
de projetos de software. A release `v1.0.0` corresponde ao primeiro MVP
homologado do produto, com gestÃ£o de usuÃ¡rios, clientes, projetos, requisitos,
tarefas, documentos, colaboraÃ§Ã£o, auditoria e visualizaÃ§Ãµes gerenciais.

## SituaÃ§Ã£o atual

| Item | SituaÃ§Ã£o |
|---|---|
| Release estÃ¡vel | `v1.0.0` |
| Baseline documental vigente | `BL-SGP-001`, revisÃ£o 1.0.1 |
| Branch estÃ¡vel | `main` |
| Commit da release `v1.0.0` | `a5bc837` |
| Tag | `v1.0.0`, publicada no GitHub |
| Testes automatizados | 95 aprovados, com 337 asserÃ§Ãµes |
| HomologaÃ§Ã£o manual | `HOM-001` a `HOM-034` aprovados |
| ProduÃ§Ã£o segura | `HOM-035` pendente de conclusÃ£o no ambiente real |
| Banco oficial | PostgreSQL |

A homologaÃ§Ã£o do MVP 1.0.0 foi aprovada com ressalva operacional. NÃ£o permanece
aberta nenhuma falha funcional impeditiva ou de alta criticidade. A ressalva
consiste em revalidar HTTPS, `APP_DEBUG=false`, webroot apontado para `public` e
permissÃµes do servidor quando o ambiente externo de produÃ§Ã£o for implantado.

## Funcionalidades da v1.0.0

- autenticaÃ§Ã£o e manutenÃ§Ã£o do perfil do usuÃ¡rio;
- administraÃ§Ã£o de usuÃ¡rios, perfis globais e ativaÃ§Ã£o ou desativaÃ§Ã£o;
- cadastro de clientes, unidades e projetos;
- equipe do projeto com papÃ©is contextuais;
- requisitos com versÃµes, prioridade, situaÃ§Ã£o e critÃ©rios de aceite;
- tarefas, subtarefas, estimativas em HH:MM e histÃ³rico;
- quadro Kanban com seis etapas configurÃ¡veis;
- geraÃ§Ã£o de documentos em DOCX e PDF, com modelos e versionamento;
- Backlog Consolidado do Projeto;
- comentÃ¡rios em projetos, requisitos e tarefas;
- anexos privados com download autorizado e remoÃ§Ã£o lÃ³gica;
- histÃ³rico consolidado e filtros por categoria;
- dashboard com oito indicadores conciliados com a base;
- calendÃ¡rio geral e por projeto, incluindo itens sem planejamento;
- cronograma Gantt bÃ¡sico, com agrupamento, atrasos e tarefas sem datas;
- pÃ¡gina inicial, login, favicon, tÃ­tulos e identidade visual prÃ³prios do SGP.

## Tecnologias

### Backend

- PHP 8.2 ou superior;
- Laravel 12.64;
- PostgreSQL 14 ou superior;
- Eloquent ORM e Blade;
- PHPWord 1.4 para documentos DOCX;
- Dompdf 3.1.6 para documentos PDF.

### Frontend

- HTML5, CSS3 e JavaScript;
- Tailwind CSS 3.4;
- Alpine.js 3.15;
- Vite 7.3.

### Qualidade e versionamento

- PHPUnit 11;
- SQLite em memÃ³ria para a suÃ­te automatizada;
- PostgreSQL para persistÃªncia oficial e homologaÃ§Ã£o integrada;
- Git e GitHub para versionamento do cÃ³digo e das releases.

## Requisitos do ambiente

- PHP 8.2 ou superior, com `dom`, `gd`, `mbstring`, `openssl`, `pdo_pgsql`,
  `tokenizer`, `xml`, `xmlwriter` e `zip`;
- Composer 2;
- PostgreSQL 14 ou superior;
- Node.js 20 ou superior e npm;
- Git.

## InstalaÃ§Ã£o

Consulte [INSTALL.md](INSTALL.md) para a instalaÃ§Ã£o completa, atualizaÃ§Ã£o,
backup, restauraÃ§Ã£o e checklist de produÃ§Ã£o.

Resumo:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm ci
npm run build
php artisan migrate --seed
php artisan sgp:create-first-administrator
php artisan optimize:clear
php artisan serve
```

Antes de executar as migrations, configure no `.env` a conexÃ£o PostgreSQL e
substitua as credenciais de exemplo. O comando interativo cria o primeiro
administrador sem distribuir usuÃ¡rio ou senha padrÃ£o.

## Testes e homologaÃ§Ã£o

Execute a suÃ­te automatizada:

```powershell
php artisan test
```

Resultado de referÃªncia da release `v1.0.0`:

```text
Tests: 95 passed
Assertions: 337
```

Os testes automatizados utilizam SQLite em memÃ³ria para rapidez e isolamento.
A homologaÃ§Ã£o manual foi executada em PostgreSQL e incluiu instalaÃ§Ã£o,
autenticaÃ§Ã£o, permissÃµes, fluxos funcionais, geraÃ§Ã£o de documentos, anexos
privados, auditoria, responsividade, compatibilidade entre navegadores, backup e
restauraÃ§Ã£o em ambiente alternativo.

O `HOM-035` deverÃ¡ ser concluÃ­do no servidor real de produÃ§Ã£o.

## Armazenamento e backup

Na `v1.0.0`, documentos e anexos sÃ£o armazenados no disco privado do Laravel,
em `storage/app/private`. Esse diretÃ³rio nÃ£o deve ser publicado nem exposto por
link pÃºblico.

O limite e as extensÃµes de anexos sÃ£o configurÃ¡veis:

```dotenv
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

O backup completo exige o dump PostgreSQL e uma cÃ³pia de
`storage/app/private` pertencentes ao mesmo ponto no tempo. O procedimento de
restauraÃ§Ã£o deve ser testado em ambiente alternativo.

## SeguranÃ§a

- cadastro pÃºblico e recuperaÃ§Ã£o pÃºblica de senha permanecem desabilitados;
- nÃ£o existem usuÃ¡rios ou senhas padrÃ£o na instalaÃ§Ã£o;
- o primeiro administrador Ã© criado por comando interativo e controlado;
- usuÃ¡rios sÃ£o administrados por perfil autorizado;
- contas sÃ£o desativadas sem exclusÃ£o fÃ­sica;
- acesso aos projetos respeita participaÃ§Ã£o ativa e papel contextual;
- anexos e documentos exigem rota autenticada e autorizaÃ§Ã£o;
- arquivos privados permanecem fora da pasta pÃºblica;
- eventos relevantes preservam usuÃ¡rio, data e hora;
- o arquivo `.env` nÃ£o Ã© versionado;
- em produÃ§Ã£o, `APP_DEBUG` deve permanecer desativado e o servidor web deve
  apontar exclusivamente para a pasta `public`.

## Baselines e evoluÃ§Ã£o

### BL-SGP-001

A `BL-SGP-001` representa o estado implementado e homologado do MVP 1.0.0.
Ela preserva o escopo histÃ³rico da release e nÃ£o deve ser alterada para incluir
funcionalidades futuras.

### AdaptaÃ§Ã£o operacional para nuvem

Antes da implantaÃ§Ã£o em uma plataforma com sistema de arquivos efÃªmero, o SGP
precisarÃ¡ substituir os usos fixos do disco local por um disco privado
configurÃ¡vel e armazenar arquivos permanentes em Object Storage. Essa correÃ§Ã£o
de compatibilidade operacional estÃ¡ prevista para a versÃ£o `v1.0.1` e nÃ£o
amplia o escopo funcional do MVP.

### BL-SGP-002

A `BL-SGP-002`, denominada FundaÃ§Ã£o SaaS Multiempresa e ConfiguraÃ§Ã£o Adaptativa
de Projetos, estÃ¡ aprovada como referÃªncia do prÃ³ximo desenvolvimento, mas
ainda nÃ£o foi implementada nem homologada.

Seu escopo comprometido inclui:

- entidade OrganizaÃ§Ã£o acima de Cliente e Projeto;
- vÃ­nculos de usuÃ¡rios e papÃ©is por organizaÃ§Ã£o;
- seleÃ§Ã£o da organizaÃ§Ã£o ativa;
- separaÃ§Ã£o entre organizaÃ§Ã£o SaaS e cliente ou demandante;
- propagaÃ§Ã£o da organizaÃ§Ã£o aos dados de negÃ³cio;
- isolamento obrigatÃ³rio de consultas, arquivos, relatÃ³rios e auditoria;
- migraÃ§Ã£o segura dos dados legados;
- configuraÃ§Ã£o independente da natureza da execuÃ§Ã£o, tratamento financeiro,
  nÃ­vel de gestÃ£o e metodologia do projeto;
- testes automatizados e manuais de isolamento entre organizaÃ§Ãµes.

## Fora do escopo atual

NÃ£o fazem parte da release `v1.0.0` nem da implementaÃ§Ã£o comprometida da
`BL-SGP-002`:

- cobranÃ§a automÃ¡tica, billing e integraÃ§Ã£o com meios de pagamento;
- subdomÃ­nios e instÃ¢ncias privadas;
- fluxo completo de contratos, baselines, mudanÃ§as, anÃ¡lises de impacto e
  aditivos;
- integraÃ§Ã£o com IA generativa e controle de consumo por organizaÃ§Ã£o;
- Wiki, Sprints, reuniÃµes, atas e gestÃ£o interna de testes;
- releases, riscos, aceite formal e Gantt avanÃ§ado.

Esses itens permanecem registrados como evoluÃ§Ã£o futura e somente entrarÃ£o em
uma entrega apÃ³s aprovaÃ§Ã£o em nova baseline.

## Fluxo de versionamento

- `main` contÃ©m somente cÃ³digo estÃ¡vel;
- `v1.0.0` identifica o commit homologado do MVP;
- a branch `release/sgp-mvp-1.0.0-rc1` permanece como registro histÃ³rico;
- correÃ§Ãµes de produÃ§Ã£o devem partir da versÃ£o estÃ¡vel;
- o desenvolvimento da `BL-SGP-002` deve ocorrer em branch prÃ³pria;
- documentaÃ§Ã£o, cÃ³digo e banco devem manter rastreabilidade com a baseline e a
  release correspondentes.

## Autoria

**Liliane de Freitas Terra Vieira**  
Analista de Requisitos, Desenvolvedora e TÃ©cnica em Tecnologia da InformaÃ§Ã£o

## LicenÃ§a

O cÃ³digo do SGP Ã© proprietÃ¡rio e nÃ£o pode ser copiado, modificado, distribuÃ­do
ou comercializado sem autorizaÃ§Ã£o expressa da autora. As condiÃ§Ãµes contratuais
e de licenciamento de cada implantaÃ§Ã£o devem ser formalizadas antes da
disponibilizaÃ§Ã£o externa. As dependÃªncias de terceiros mantÃªm suas respectivas
licenÃ§as.
