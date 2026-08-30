# SGP · Sistema de Gestão de Projetos de Software

> **Estrutura conectada. Projetos organizados, decisões rastreáveis e software em evolução.**

O **SGP** é uma plataforma SaaS para planejar, executar, documentar e acompanhar projetos de software com rastreabilidade ponta a ponta. O sistema integra iniciativas, oportunidades, projetos, requisitos, tarefas, documentos, contratos, baselines, mudanças, testes, evidências, organizações e auditoria em um único ambiente.

O SGP pode apoiar a operacionalização de processos, a rastreabilidade e a produção de evidências úteis em uma jornada de melhoria e avaliação MPS.BR. O uso da ferramenta não certifica a organização nem atribui nível de maturidade.

## Situação da release

| Referência | Situação |
|---|---|
| Release candidata | `v3.0.0` |
| Baseline | `BL-SGP-003 · Engenharia Documental Adaptativa` |
| Revisão documental | `2.1` |
| Homologação funcional | Aprovada em 30/08/2026 |
| Suíte automatizada | 367 testes aprovados, 1.473 asserções |
| Branch de estabilização | `feature/bl3-p09-comunicacao-acesso` |
| Branch de produção | `main`, após promoção controlada |
| Commit homologado | A registrar após o merge final |
| Tag planejada | `v3.0.0`, criada somente no commit publicado |
| Identidade visual | Visual vigente preservado; reformulação planejada para a BL-SGP-004 |

Esta cópia é uma **candidata final de release**. O congelamento técnico somente se completa quando o commit efetivamente implantado na `main`, a tag `v3.0.0` e as evidências do Laravel Cloud forem registrados no pacote de encerramento.

## Capacidades entregues

- fundação SaaS multiempresa com isolamento de dados, arquivos e auditoria;
- projetos adaptativos, requisitos, tarefas, Kanban, calendário e cronograma;
- entrada por iniciativa, oportunidade comercial e conversão controlada em projeto;
- aplicabilidade e governança proporcionais ao contexto;
- artefatos estruturados, revisão, aprovação e publicações DOCX/PDF;
- contratos, baselines de projeto e itens congelados;
- solicitações de mudança com análise de impacto e implementação rastreável;
- casos de teste, execuções, evidências e matriz de rastreabilidade;
- comunicação transacional por SMTP, diagnóstico sanitizado e fila;
- recuperação pública de senha, reenvio administrativo e senha temporária;
- auditoria organizacional e trilha global de segurança exclusiva do Superadmin.

## Segurança operacional

- cadastro público desabilitado;
- inexistência de credencial padrão distribuída com o sistema;
- autenticação e autorização por perfil global, vínculo organizacional e projeto;
- contas inativas impedidas de autenticar ou manter sessão ativa;
- arquivos permanentes em armazenamento privado;
- segredos somente em variáveis protegidas do ambiente;
- senha SMTP, tokens e senhas temporárias nunca persistidos em logs ou auditoria;
- recuperação de senha com resposta neutra, expiração, uso único e limitação de solicitações;
- eventos globais de autenticação, senha e comunicação separados da auditoria de cada organização.

## Tecnologias

| Camada | Tecnologias principais |
|---|---|
| Backend | PHP 8.2+, Laravel 12, Eloquent ORM e Blade |
| Banco | PostgreSQL 14+ |
| Frontend | Tailwind CSS, Alpine.js e Vite |
| Documentos | PHPWord e Dompdf |
| Arquivos privados | Flysystem local ou Object Storage compatível com S3 |
| Comunicação | SMTP transacional e filas Laravel |
| Qualidade | PHPUnit, testes automatizados e homologação funcional |
| Versionamento | Git, GitHub, branches controladas, tags e releases rastreáveis |

## Instalação e atualização

Consulte [INSTALL.md](INSTALL.md). O roteiro inclui ambiente local, PostgreSQL, Object Storage, SMTP, filas, Laravel Cloud, backup, restauração, atualização e retorno de versão.

Resumo local:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm ci
npm run build
php artisan migrate --seed
php artisan optimize:clear
php artisan serve
```

Em outro terminal, quando `QUEUE_CONNECTION=database`:

```powershell
php artisan queue:work --tries=3 --timeout=90
```

## Validação

```powershell
php artisan optimize:clear
php artisan test
git diff --check
```

Resultado de referência da candidata final:

```text
Tests: 367 passed
Assertions: 1473
```

Além da suíte automatizada, a homologação funcional cobriu os percursos P01 a P09, incluindo SMTP real, redefinição pública de senha, senha temporária, troca obrigatória e auditorias global e organizacional.

## Baselines do produto

| Baseline | Nome | Situação |
|---|---|---|
| `BL-SGP-001` | MVP | Implementada, homologada e histórica |
| `BL-SGP-002` | Fundação SaaS Multiempresa | Implementada, homologada e histórica |
| `BL-SGP-003` | Engenharia Documental Adaptativa | Implementada e homologada; publicação `v3.0.0` em fechamento |
| `BL-SGP-004` | Evolução posterior | Não iniciada; escopo depende de análise e aprovação |

## Evoluções posteriores

Os itens abaixo não integram a `v3.0.0` e exigem decisão própria para a BL-SGP-004:

- reformulação da identidade visual e experiência responsiva;
- assistência contextual com IA;
- cobrança automática e integração com meios de pagamento;
- subdomínios e instâncias privadas por organização;
- Wiki, reuniões, atas, Sprints e Gantt avançado;
- qualquer mudança identificada durante a preparação comercial.

## Documentação da release

O encerramento da BL-SGP-003 fica em [`docs/baselines/BL-SGP-003/06-Encerramento-Homologacao`](docs/baselines/BL-SGP-003/06-Encerramento-Homologacao), com registro da release, catálogo, roteiro de promoção, homologação e manifesto de integridade.

## Versionamento e congelamento

- `main` representa somente código estável e publicável;
- a tag `v3.0.0` deve apontar para o commit realmente implantado;
- o SHA da release não deve ser antecipado nem inventado;
- correção posterior exige novo commit e nova versão, sem mover a tag histórica;
- mudança de escopo após o congelamento exige registro e baseline de destino;
- documentação, código, migrations, testes, evidências e release permanecem vinculados.

## Autoria e licença

**Liliane de Freitas Terra Vieira**
Analista de Requisitos, Desenvolvedora e Técnica em Tecnologia da Informação

O SGP é software proprietário. Sua cópia, modificação, distribuição, implantação ou comercialização depende de autorização e instrumento contratual aplicável. As dependências de terceiros conservam suas próprias licenças.
