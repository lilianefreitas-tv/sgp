# SGP - Sistema de Gestão de Projetos de Software

O SGP centraliza o planejamento, a execução, a documentação e a
rastreabilidade de projetos de software. A release `v1.0.0` corresponde ao MVP
homologado, com gestão de usuários, clientes, projetos, requisitos, tarefas,
Kanban, documentos, colaboração, histórico e visualizações gerenciais.

## Status

**Release:** MVP 1.0.0  
**Situação:** pronta para homologação final integrada  
**Testes automatizados:** 93 cenários  
**Banco oficial:** PostgreSQL

## Funcionalidades do MVP

- autenticação e perfil do usuário;
- administração de usuários, perfis globais e ativação ou desativação;
- cadastro de clientes, unidades e projetos;
- equipe do projeto com papéis contextuais;
- requisitos com versões, prioridade, situação e critérios de aceite;
- tarefas, subtarefas, estimativa em HH:MM e histórico;
- quadro Kanban com seis etapas configuráveis;
- documentos em DOCX e PDF, com modelos e versionamento;
- Backlog Consolidado do Projeto;
- comentários e anexos privados;
- histórico consolidado do projeto;
- dashboard com indicadores reais;
- calendário geral e por projeto;
- cronograma Gantt básico;
- página inicial, login e identidade visual próprios do SGP.

## Tecnologias

### Backend

- PHP 8.2 ou superior;
- Laravel 12.64;
- PostgreSQL;
- Eloquent ORM e Blade;
- PHPWord 1.4 para DOCX;
- Dompdf 3.1.6 para PDF.

### Frontend

- HTML5, CSS3 e JavaScript;
- Tailwind CSS 3.4;
- Alpine.js 3.15;
- Vite 7.3.

## Requisitos do ambiente

- PHP 8.2 ou superior, com `dom`, `gd`, `mbstring`, `openssl`, `pdo_pgsql`,
  `tokenizer`, `xml`, `xmlwriter` e `zip`;
- Composer 2;
- PostgreSQL 14 ou superior;
- Node.js 20 ou superior e npm;
- Git, recomendado para versionamento.

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
troque credenciais de exemplo. O comando interativo cria o primeiro
administrador sem distribuir usuário ou senha padrão.

## Testes

```powershell
php artisan test
```

Resultado esperado:

```text
Tests: 93 passed
```

O conjunto automatizado utiliza SQLite em memória para rapidez e isolamento. A
homologação final também prevê instalação e testes manuais em PostgreSQL.

## Armazenamento

Documentos e anexos são armazenados no disco privado do Laravel. Não publique
`storage/app/private` nem crie link público para esse diretório.

O limite e as extensões de anexos são configuráveis:

```dotenv
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

## Segurança

- cadastro público e recuperação pública de senha permanecem desabilitados;
- não existem usuários ou senhas padrão na instalação;
- o primeiro administrador é criado por comando interativo e controlado;
- usuários são administrados por perfil autorizado;
- contas são desativadas sem exclusão física;
- acesso aos projetos respeita participação ativa e papel contextual;
- anexos e documentos exigem rota autenticada e autorização;
- eventos relevantes preservam usuário, data e hora.

## Escopo posterior ao MVP

Wiki por projeto, Sprints, reuniões e atas, testes dentro do sistema, releases,
riscos, mudanças, aceite, Gantt avançado e recursos de IA permanecem registrados
para versões futuras.

## Autoria

**Liliane de Freitas Terra Vieira**  
Analista de Requisitos, Desenvolvedora e Técnica em Tecnologia da Informação

## Licença

O código do SGP é proprietário e não pode ser copiado, modificado, distribuído
ou comercializado sem autorização expressa da autora. As condições contratuais
e de licenciamento de cada implantação devem ser formalizadas antes da
disponibilização externa. As dependências de terceiros mantêm suas respectivas
licenças.
