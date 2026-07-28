# SGP - Sistema de Gestão de Projetos de Software

O SGP centraliza o planejamento, a execução, a documentação e a rastreabilidade
de projetos de software. A release `v1.0.0` corresponde ao primeiro MVP
homologado do produto, com gestão de usuários, clientes, projetos, requisitos,
tarefas, documentos, colaboração, auditoria e visualizações gerenciais.

## Situação atual

| Item | Situação |
|---|---|
| Release estável | `v1.0.0` |
| Baseline documental vigente | `BL-SGP-001`, revisão 1.0.1 |
| Branch estável | `main` |
| Commit da release `v1.0.0` | `a5bc837` |
| Tag | `v1.0.0`, publicada no GitHub |
| Testes automatizados | 95 aprovados, com 337 asserções |
| Homologação manual | `HOM-001` a `HOM-034` aprovados |
| Produção segura | `HOM-035` pendente de conclusão no ambiente real |
| Banco oficial | PostgreSQL |

A homologação do MVP 1.0.0 foi aprovada com ressalva operacional. Não permanece
aberta nenhuma falha funcional impeditiva ou de alta criticidade. A ressalva
consiste em revalidar HTTPS, `APP_DEBUG=false`, webroot apontado para `public` e
permissões do servidor quando o ambiente externo de produção for implantado.

## Funcionalidades da v1.0.0

- autenticação e manutenção do perfil do usuário;
- administração de usuários, perfis globais e ativação ou desativação;
- cadastro de clientes, unidades e projetos;
- equipe do projeto com papéis contextuais;
- requisitos com versões, prioridade, situação e critérios de aceite;
- tarefas, subtarefas, estimativas em HH:MM e histórico;
- quadro Kanban com seis etapas configuráveis;
- geração de documentos em DOCX e PDF, com modelos e versionamento;
- Backlog Consolidado do Projeto;
- comentários em projetos, requisitos e tarefas;
- anexos privados com download autorizado e remoção lógica;
- histórico consolidado e filtros por categoria;
- dashboard com oito indicadores conciliados com a base;
- calendário geral e por projeto, incluindo itens sem planejamento;
- cronograma Gantt básico, com agrupamento, atrasos e tarefas sem datas;
- página inicial, login, favicon, títulos e identidade visual próprios do SGP.

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
- SQLite em memória para a suíte automatizada;
- PostgreSQL para persistência oficial e homologação integrada;
- Git e GitHub para versionamento do código e das releases.

## Requisitos do ambiente

- PHP 8.2 ou superior, com `dom`, `gd`, `mbstring`, `openssl`, `pdo_pgsql`,
  `tokenizer`, `xml`, `xmlwriter` e `zip`;
- Composer 2;
- PostgreSQL 14 ou superior;
- Node.js 20 ou superior e npm;
- Git.

## Instalação

Consulte [INSTALL.md](INSTALL.md) para a instalação completa, atualização,
backup, restauração e checklist de produção.

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

Antes de executar as migrations, configure no `.env` a conexão PostgreSQL e
substitua as credenciais de exemplo. O comando interativo cria o primeiro
administrador sem distribuir usuário ou senha padrão.

## Testes e homologação

Execute a suíte automatizada:

```powershell
php artisan test
```

Resultado de referência da release `v1.0.0`:

```text
Tests: 95 passed
Assertions: 337
```

Os testes automatizados utilizam SQLite em memória para rapidez e isolamento.
A homologação manual foi executada em PostgreSQL e incluiu instalação,
autenticação, permissões, fluxos funcionais, geração de documentos, anexos
privados, auditoria, responsividade, compatibilidade entre navegadores, backup e
restauração em ambiente alternativo.

O `HOM-035` deverá ser concluído no servidor real de produção.

## Armazenamento e backup

Na `v1.0.0`, documentos e anexos são armazenados no disco privado do Laravel,
em `storage/app/private`. Esse diretório não deve ser publicado nem exposto por
link público.

O limite e as extensões de anexos são configuráveis:

```dotenv
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

O backup completo exige o dump PostgreSQL e uma cópia de
`storage/app/private` pertencentes ao mesmo ponto no tempo. O procedimento de
restauração deve ser testado em ambiente alternativo.

## Segurança

- cadastro público e recuperação pública de senha permanecem desabilitados;
- não existem usuários ou senhas padrão na instalação;
- o primeiro administrador é criado por comando interativo e controlado;
- usuários são administrados por perfil autorizado;
- contas são desativadas sem exclusão física;
- acesso aos projetos respeita participação ativa e papel contextual;
- anexos e documentos exigem rota autenticada e autorização;
- arquivos privados permanecem fora da pasta pública;
- eventos relevantes preservam usuário, data e hora;
- o arquivo `.env` não é versionado;
- em produção, `APP_DEBUG` deve permanecer desativado e o servidor web deve
  apontar exclusivamente para a pasta `public`.

## Baselines e evolução

### BL-SGP-001

A `BL-SGP-001` representa o estado implementado e homologado do MVP 1.0.0.
Ela preserva o escopo histórico da release e não deve ser alterada para incluir
funcionalidades futuras.

### Adaptação operacional para nuvem

Antes da implantação em uma plataforma com sistema de arquivos efêmero, o SGP
precisará substituir os usos fixos do disco local por um disco privado
configurável e armazenar arquivos permanentes em Object Storage. Essa correção
de compatibilidade operacional está prevista para a versão `v1.0.1` e não
amplia o escopo funcional do MVP.

### BL-SGP-002

A `BL-SGP-002`, denominada Fundação SaaS Multiempresa e Configuração Adaptativa
de Projetos, está aprovada como referência do próximo desenvolvimento, mas
ainda não foi implementada nem homologada.

Seu escopo comprometido inclui:

- entidade Organização acima de Cliente e Projeto;
- vínculos de usuários e papéis por organização;
- seleção da organização ativa;
- separação entre organização SaaS e cliente ou demandante;
- propagação da organização aos dados de negócio;
- isolamento obrigatório de consultas, arquivos, relatórios e auditoria;
- migração segura dos dados legados;
- configuração independente da natureza da execução, tratamento financeiro,
  nível de gestão e metodologia do projeto;
- testes automatizados e manuais de isolamento entre organizações.

## Fora do escopo atual

Não fazem parte da release `v1.0.0` nem da implementação comprometida da
`BL-SGP-002`:

- cobrança automática, billing e integração com meios de pagamento;
- subdomínios e instâncias privadas;
- fluxo completo de contratos, baselines, mudanças, análises de impacto e
  aditivos;
- integração com IA generativa e controle de consumo por organização;
- Wiki, Sprints, reuniões, atas e gestão interna de testes;
- releases, riscos, aceite formal e Gantt avançado.

Esses itens permanecem registrados como evolução futura e somente entrarão em
uma entrega após aprovação em nova baseline.

## Fluxo de versionamento

- `main` contém somente código estável;
- `v1.0.0` identifica o commit homologado do MVP;
- a branch `release/sgp-mvp-1.0.0-rc1` permanece como registro histórico;
- correções de produção devem partir da versão estável;
- o desenvolvimento da `BL-SGP-002` deve ocorrer em branch própria;
- documentação, código e banco devem manter rastreabilidade com a baseline e a
  release correspondentes.

## Autoria

**Liliane de Freitas Terra Vieira**  
Analista de Requisitos, Desenvolvedora e Técnica em Tecnologia da Informação

## Licença

O código do SGP é proprietário e não pode ser copiado, modificado, distribuído
ou comercializado sem autorização expressa da autora. As condições contratuais
e de licenciamento de cada implantação devem ser formalizadas antes da
disponibilização externa. As dependências de terceiros mantêm suas respectivas
licenças.
