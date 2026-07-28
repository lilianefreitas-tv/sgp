# SGP - Sistema de Gestão de Projetos de Software

O SGP centraliza o planejamento, a execução, a documentação e a rastreabilidade
de projetos de software. A release `v1.0.1` preserva o escopo funcional do
primeiro MVP homologado e acrescenta a compatibilidade operacional necessária
para implantação em plataformas com filesystem efêmero, como o Laravel Cloud.

## Situação atual

| Item | Situação |
|---|---|
| Release estável | `v1.0.1` |
| Baseline de referência | `BL-SGP-001`, com correção operacional `v1.0.1` |
| Branch estável | `main` |
| Base homologada | `v1.0.0`, commit `a5bc837` |
| Testes automatizados | 100 aprovados, com 358 asserções |
| Homologação manual | `HOM-001` a `HOM-034` aprovados |
| Produção segura | `HOM-035` pendente de conclusão no ambiente real |
| Banco oficial | PostgreSQL |

A homologação funcional do MVP 1.0.0 foi aprovada. A `v1.0.1` implementa o
armazenamento privado configurável, a persistência em Object Storage e o
bootstrap não interativo do primeiro administrador. O `HOM-035` permanece
pendente para validar HTTPS, `APP_DEBUG=false`, recursos vinculados e
persistência dos arquivos após novo deploy no ambiente real.

## Funcionalidades preservadas na v1.0.1

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
- Dompdf 3.1.6 para documentos PDF;
- Flysystem AWS S3 v3 para Object Storage compatível com S3.

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
- Git;
- Object Storage compatível com S3 quando o filesystem da plataforma for
  efêmero.

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
substitua as credenciais de exemplo. Em instalações locais ou com terminal
interativo, o comando cria o primeiro administrador sem distribuir usuário ou
senha padrão. O procedimento não interativo para nuvem está descrito no
[INSTALL.md](INSTALL.md).

## Testes e homologação

Execute a suíte automatizada:

```powershell
php artisan test
```

Resultado de referência da release `v1.0.1`:

```text
Tests: 100 passed
Assertions: 358
```

Os testes automatizados utilizam SQLite em memória para rapidez e isolamento.
A homologação manual foi executada em PostgreSQL e incluiu instalação,
autenticação, permissões, fluxos funcionais, geração de documentos, anexos
privados, auditoria, responsividade, compatibilidade entre navegadores, backup e
restauração em ambiente alternativo.

O `HOM-035` deverá ser concluído no servidor real de produção.

## Armazenamento e backup

Na `v1.0.1`, documentos e anexos usam um disco privado configurável. Em
instalações locais, o padrão permanece `local`, em `storage/app/private`. Em
plataformas com filesystem efêmero, deve ser usado um bucket privado compatível
com S3.

O disco, o limite e as extensões de anexos são configuráveis:

```dotenv
SGP_PRIVATE_DISK=local
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

Quando `SGP_PRIVATE_DISK` não é definido, o SGP herda `FILESYSTEM_DISK`. No
Laravel Cloud, o bucket vinculado injeta essa variável e as credenciais
compatíveis com S3. O bucket deve ser privado.

O backup completo exige o dump PostgreSQL e uma cópia consistente do disco
privado, local ou remoto, pertencentes ao mesmo ponto no tempo. O procedimento
de restauração deve ser testado em ambiente alternativo.

## Segurança

- cadastro público e recuperação pública de senha permanecem desabilitados;
- não existem usuários ou senhas padrão na instalação;
- o primeiro administrador é criado por procedimento interativo ou bootstrap
  não interativo controlado;
- usuários são administrados por perfil autorizado;
- contas são desativadas sem exclusão física;
- acesso aos projetos respeita participação ativa e papel contextual;
- anexos e documentos exigem rota autenticada e autorização;
- arquivos privados permanecem fora da pasta pública ou em bucket privado;
- eventos relevantes preservam usuário, data e hora;
- o arquivo `.env` não é versionado;
- em produção, `APP_DEBUG` deve permanecer desativado e o servidor web deve
  apontar exclusivamente para a pasta `public`.

## Baselines e evolução

### BL-SGP-001

A `BL-SGP-001` representa o estado implementado e homologado do MVP 1.0.0.
Ela preserva o escopo histórico da release e não deve ser alterada para incluir
funcionalidades futuras.

### Correção operacional v1.0.1

A `v1.0.1` remove os usos fixos do disco local nos fluxos permanentes, permite
configurar o disco privado, gera DOCX e PDF em arquivos temporários e envia os
resultados ao armazenamento persistente. Também permite criar o primeiro
administrador de forma não interativa usando uma variável protegida e
temporária. A correção não altera o escopo funcional do MVP nem antecipa a
`BL-SGP-002`.

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

Não fazem parte da release `v1.0.1` nem da implementação comprometida da
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
- `v1.0.1` identifica a correção operacional para implantação em nuvem;
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
