# SGP · Sistema de Gestão de Projetos de Software

> **Estrutura conectada. Projetos organizados, decisões rastreáveis e software em evolução.**

O **SGP** é uma plataforma SaaS para planejar, executar, documentar e acompanhar projetos de software com rastreabilidade ponta a ponta. O sistema integra requisitos, tarefas, documentos, equipes, organizações, histórico e indicadores em um único ambiente, preservando as decisões que explicam a evolução de cada projeto.

Seu diferencial não é apenas armazenar informações. É transformar a gestão do projeto em **evidência clara, verificável e reutilizável**, com documentação precisa, leitura acessível e apoio aos processos de software alinhados ao **MPS.BR**.

---

## ✨ Visão do produto

O SGP foi concebido para reduzir a fragmentação entre planejamento, execução, documentação e governança. Cada necessidade pode ser relacionada ao requisito, regra, fluxo, tarefa, teste, evidência e release correspondente.

```text
Necessidade → Requisito → Regra → Fluxo → Tarefa → Teste → Evidência → Release
```

> **Princípio central:** nenhuma evolução deve apagar o caminho percorrido. Baselines congeladas permanecem preservadas, mudanças são analisadas e cada release mantém sua referência funcional, documental e técnica.

## 🚀 Situação atual

| Referência | Situação atual |
|---|---|
| Release em produção | `v2.0.1`, promovida a partir da `v2.0.1-RC2` homologada |
| Baseline vigente | `BL-SGP-002 · Fundação SaaS Multiempresa` |
| Branch de produção | `main` |
| Commit homologado | `dffc225` |
| Ambiente | Laravel Cloud, com aplicação e PostgreSQL de produção ativos |
| Administração inicial | Superadministrador criado no ambiente produtivo |
| Homologação | Encerrada com 100% de aprovação declarada |
| Testes automatizados | 186 aprovados, com 807 asserções |
| Integração contínua | 2 checks aprovados no GitHub |
| Próxima evolução | `BL-SGP-003 · Engenharia Documental Adaptativa` |

A `BL-SGP-002` está **implementada, homologada e implantada em produção**. O ambiente produtivo utiliza a branch `main`, possui banco PostgreSQL configurado e já conta com o usuário superadministrador necessário à operação inicial.

A referência técnica da homologação é o commit `dffc225`, originado do PR `#2`. A baseline permanece congelada como fonte funcional da release atualmente promovida.

## 🧩 O que o SGP já entrega

### Gestão de organizações e operação SaaS

- cadastro, edição, ativação e suspensão de organizações;
- conta de usuário global, com participação em múltiplas organizações;
- papéis e permissões independentes por vínculo organizacional;
- seleção segura da organização ativa;
- isolamento de consultas, gravações, arquivos, documentos e auditoria;
- bloqueio de vínculos indevidos entre organizações;
- códigos e configurações próprios por organização;
- administração de membros e demandantes no contexto correto.

### Gestão adaptativa de projetos

- cadastro de clientes, unidades, projetos e equipes;
- papéis contextuais por projeto;
- definição independente da natureza da execução;
- tratamento financeiro configurável, inclusive projetos sem controle monetário;
- nível de gestão e metodologia tratados como dimensões independentes;
- alteração de configuração sem perda do histórico;
- explicações contextuais sobre os efeitos das escolhas do projeto.

### Planejamento e execução

- requisitos com versões, prioridade, situação e critérios de aceite;
- tarefas, subtarefas, responsáveis, estimativas em `HH:MM` e histórico;
- quadro Kanban com seis etapas configuráveis;
- Backlog Consolidado do Projeto;
- calendário geral e por projeto;
- cronograma Gantt básico, com agrupamentos, atrasos e itens sem datas;
- comentários em projetos, requisitos e tarefas;
- dashboard com indicadores conciliados com a base.

### Documentação, arquivos e rastreabilidade

- geração de documentos em DOCX e PDF;
- modelos documentais e controle de versões;
- anexos privados com autorização de acesso e remoção lógica;
- armazenamento privado local ou compatível com S3;
- histórico consolidado com filtros por categoria;
- auditoria de eventos relevantes com usuário, data, hora e organização;
- preservação das referências entre baseline, release, código, banco e evidências.

## 🛡️ Segurança e isolamento

- cadastro público desabilitado;
- inexistência de usuário ou senha padrão distribuídos com o sistema;
- criação controlada do primeiro administrador;
- contas desativadas sem exclusão física;
- autorização por papel global, vínculo organizacional e participação no projeto;
- arquivos privados fora da pasta pública ou em bucket privado;
- segregação obrigatória dos dados por organização;
- bloqueio de chaves relacionadas a organizações diferentes;
- `.env` não versionado;
- `APP_DEBUG=false` no ambiente de produção;
- servidor web direcionado exclusivamente à pasta `public`.

> A entrega externa de mensagens por e-mail ainda não integra a capacidade operacional vigente. A configuração SMTP completa e sua homologação ponta a ponta estão comprometidas com a `BL-SGP-003`.

## 🧱 Arquitetura e tecnologias

| Camada | Tecnologias principais |
|---|---|
| Backend | PHP 8.2+, Laravel 12.64, Eloquent ORM e Blade |
| Banco de dados | PostgreSQL 14+ |
| Frontend | HTML5, CSS3, JavaScript, Tailwind CSS 3.4 e Alpine.js 3.15 |
| Build | Vite 7.3 e Node.js 20+ |
| Documentos | PHPWord 1.4 e Dompdf 3.1.6 |
| Arquivos privados | Flysystem, armazenamento local ou Object Storage compatível com S3 |
| Qualidade | PHPUnit 11, testes automatizados e homologação integrada |
| Versionamento | Git, GitHub, branches controladas, commits e releases rastreáveis |

## ⚙️ Requisitos do ambiente

- PHP 8.2 ou superior, com `dom`, `gd`, `mbstring`, `openssl`, `pdo_pgsql`, `tokenizer`, `xml`, `xmlwriter` e `zip`;
- Composer 2;
- PostgreSQL 14 ou superior;
- Node.js 20 ou superior e npm;
- Git;
- Object Storage compatível com S3 quando a plataforma utilizar filesystem efêmero.

## 🛠️ Instalação

Consulte [INSTALL.md](INSTALL.md) para os procedimentos completos de instalação, atualização, backup, restauração e validação do ambiente.

Resumo para ambiente local:

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

Antes de executar as migrations, configure a conexão PostgreSQL no `.env` e substitua todas as credenciais de exemplo. A instalação não distribui usuário nem senha padrão.

## ✅ Testes e homologação

Execute a suíte automatizada:

```powershell
php artisan test
```

Resultado de referência da release homologada:

```text
Tests: 186 passed
Assertions: 807
```

A homologação da `v2.0.1-RC2` foi encerrada com aprovação integral declarada, contemplando build de produção, integração contínua, deploy no Laravel Cloud, migrations, autenticação, navegação, isolamento multiempresa, fluxos principais, persistência e análise de logs.

## 💾 Armazenamento e backup

Documentos e anexos utilizam disco privado configurável. Em ambiente local, o padrão é `storage/app/private`. Em plataformas com filesystem efêmero, deve ser utilizado um bucket privado compatível com S3.

```dotenv
SGP_PRIVATE_DISK=local
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

Quando `SGP_PRIVATE_DISK` não é definido, o SGP herda `FILESYSTEM_DISK`. O backup completo deve reunir o dump PostgreSQL e uma cópia consistente do armazenamento privado pertencentes ao mesmo ponto no tempo. A restauração deve ser testada em ambiente alternativo.

## 🗂️ Baselines do produto

| Baseline | Nome | Situação |
|---|---|---|
| `BL-SGP-001` | MVP do Sistema de Gestão de Projetos | Implementada, homologada e preservada como histórico |
| `BL-SGP-002` | Fundação SaaS Multiempresa | Implementada, homologada e em produção |
| `BL-SGP-003` | Engenharia Documental Adaptativa | Aprovada para implementação |

### BL-SGP-001 · MVP

Estabeleceu a fundação funcional do SGP: usuários, clientes, projetos, equipes, requisitos, tarefas, Kanban, documentos, anexos, histórico, dashboard, calendário e Gantt básico. Sua referência permanece congelada e não recebe funcionalidades posteriores.

### BL-SGP-002 · Fundação SaaS Multiempresa

Transformou o MVP em uma fundação SaaS multiempresa. A baseline introduziu organizações, vínculos e papéis organizacionais, seleção de contexto, isolamento de dados e arquivos, migração do legado e configuração adaptativa dos projetos.

### BL-SGP-003 · Engenharia Documental Adaptativa

A próxima baseline está **aprovada para implementação**, mas ainda não deve ser apresentada como funcionalidade disponível. Seu propósito é tornar o SGP capaz de governar o ciclo completo dos documentos e das mudanças do projeto, com profundidade suficiente para produzir artefatos precisos, compreensíveis, auditáveis e úteis como evidência de processos.

#### Ciclo documental adaptativo

O fluxo será ajustado ao nível de gestão do projeto, preservando estados inequívocos e responsabilidades claras:

```text
Rascunho → Pendente de validação → Revisado → Aprovado → Congelado
                                      ↓
                         Substituído ou arquivado
```

O sistema deverá distinguir documento em elaboração, documento aprovado para orientar implementação, artefato implementado, resultado homologado e conteúdo futuro. O congelamento impedirá alterações silenciosas, sem bloquear revisões ou mudanças formalmente controladas.

#### Escopo funcional aprovado, RF087 a RF100

| Faixa | Capacidades comprometidas |
|---|---|
| `RF087 a RF089` | Contratos, vigência, vínculo com baseline, valores, capacidade e unidades contratadas |
| `RF090 a RF093` | Solicitação de mudança, análise de impacto, tratamento financeiro e estimativas de esforço, prazo, custo e preço |
| `RF094 a RF097` | Proposta e comparação de baselines, projeção de esgotamento, propostas, aditivos e aprovações |
| `RF098 a RF100` | Documentação comprobatória, indicadores contratuais e financeiros, histórico e trilha de auditoria |

#### Capacidades complementares comprometidas

- gestão completa de baselines, itens de baseline e congelamento;
- fluxo adaptativo de elaboração, revisão, validação, aprovação, substituição e arquivamento;
- controle formal de mudanças com análise de impacto funcional, técnico, financeiro, contratual e documental;
- testes, homologação, registro de resultados e organização de evidências;
- matriz de rastreabilidade de apoio aos resultados esperados do MPS.BR;
- geração de artefatos legíveis em DOCX e PDF, com versionamento e histórico;
- configuração SMTP completa, incluindo remetente, domínio e validação de entrega;
- `RM-004`, com redefinição administrativa de senha por superadministrador, senha temporária e troca obrigatória no primeiro acesso;
- migrations incrementais, preservando os dados e as baselines anteriores;
- auditoria de decisões, responsáveis, datas, versões e transições de estado;
- validação humana obrigatória para aprovações, congelamentos e decisões de homologação.

> **Compromisso de qualidade:** o SGP apoiará a organização, a rastreabilidade e a produção de evidências relacionadas ao MPS.BR. A ferramenta não declarará conformidade, maturidade ou aprovação de processo de forma automática, pois essas conclusões dependem do contexto organizacional, da execução real e da avaliação competente.

## 🔭 Evoluções posteriores

Os itens abaixo permanecem no roadmap e **não integram a implementação comprometida da BL-SGP-003**:

- assistência contextual com IA generativa;
- aprovação, congelamento ou decisão automática por IA;
- cobrança automática e integração com meios de pagamento;
- subdomínios e instâncias privadas por organização;
- Wiki, reuniões e atas colaborativas;
- Sprints e Gantt avançado;
- funcionalidades adicionais que ainda dependam de análise e aprovação em nova baseline.

## 🔁 Fluxo de versionamento

- `main` representa o código estável e atualmente implantado em produção;
- `dffc225` é a referência técnica imutável da homologação `v2.0.1`;
- baselines documentais e releases de software são relacionadas, mas não confundidas;
- uma baseline aprovada para implementação não é anunciada como software pronto;
- correções de produção devem partir da versão estável;
- novas capacidades exigem registro, análise de impacto e baseline de destino;
- documentação, código, migrations, testes, evidências e release devem permanecer rastreáveis entre si;
- baselines congeladas não são alteradas silenciosamente.

## 👩‍💻 Autoria

**Liliane de Freitas Terra Vieira**<br>
Analista de Requisitos, Desenvolvedora e Técnica em Tecnologia da Informação

## 📄 Licença

O código do SGP é proprietário e não pode ser copiado, modificado, distribuído ou comercializado sem autorização expressa da autora. As condições contratuais e de licenciamento de cada implantação devem ser formalizadas antes da disponibilização externa. As dependências de terceiros mantêm suas respectivas licenças.
