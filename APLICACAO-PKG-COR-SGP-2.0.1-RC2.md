# PKG-COR-SGP-2.0.1-RC2

## 1. Identificação

- Mudança principal: `MUD-HOM-SENHA-001 — Recuperação e redefinição de acesso`
- Baseline de origem: `BL-SGP-002 — Fundação SaaS`
- Release de origem: `v2.0.1-rc1`
- Candidata de destino: `v2.0.1-rc2`
- Branch: `agent/v2.0.1-rc2`
- Situação do pacote: implementado e pendente de validação automatizada completa e homologação

## 2. Conteúdo do pacote

O pacote entrega, em uma única aplicação:

1. opção **Esqueceu a senha?** no login;
2. resposta pública neutra, sem confirmar se o e-mail existe;
3. envio somente para contas ativas;
4. reenvio pelo Superadmin na gestão de contas globais;
5. reenvio pelo Proprietário ou Administrador para contas ativas vinculadas à própria organização;
6. bloqueio para Membro, Leitor e tentativa entre organizações;
7. limite de solicitações na rota e no broker de senhas;
8. token temporário de 60 minutos, de uso único, com invalidação do anterior;
9. envio automático do primeiro acesso por e-mail;
10. remoção da exposição do token e da URL na interface;
11. auditoria própria de eventos de segurança, sem token ou senha;
12. identificação centralizada da versão por `SGP_RELEASE_LABEL`;
13. testes automatizados de recuperação, permissões, conta inativa e isolamento.

## 3. Pré-requisitos

- PHP 8.2 ou superior;
- Composer;
- Node.js e npm;
- PostgreSQL do ambiente local ou banco de testes isolado;
- branch `agent/v2.0.1-rc1` disponível localmente;
- serviço de e-mail transacional configurado para a homologação;
- backup ou snapshot do banco de homologação antes do deploy.

Nunca copie credenciais para o repositório, documento, captura de tela ou comando compartilhado.

## 4. Aplicação no desenvolvimento local

Partindo do repositório do SGP:

```bash
git fetch origin
git switch agent/v2.0.1-rc1
git pull --ff-only origin agent/v2.0.1-rc1
git switch -c agent/v2.0.1-rc2
```

Se o pacote tiver sido recebido como arquivo `.patch`, aplique-o na raiz do repositório:

```bash
git apply --check PKG-COR-SGP-2.0.1-RC2.patch
git apply PKG-COR-SGP-2.0.1-RC2.patch
```

Instale as dependências e prepare o ambiente:

```bash
composer install
npm ci
php artisan config:clear
```

No `.env` local, confirme pelo menos:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
SGP_RELEASE_LABEL="Versão 2.0.1 RC2 • Desenvolvimento"
MAIL_MAILER=log
```

Execute a migration e as validações:

```bash
php artisan migrate
php artisan test
npm run build
```

O pacote não deve ser publicado se algum teste falhar.

## 5. Commit e envio da candidata

Depois dos testes locais aprovados:

```bash
git status
git diff --check
git add .
git commit -m "fix: implementa recuperação segura de acesso"
git push -u origin agent/v2.0.1-rc2
```

Antes do commit, confirme que não foram incluídos `.env`, logs, dumps, tokens, senhas ou arquivos temporários.

## 6. Configuração da homologação no Laravel Cloud

Configure a aplicação de homologação para acompanhar a branch `agent/v2.0.1-rc2`.

Variáveis obrigatórias:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://ENDERECO-EXATO-DA-HOMOLOGACAO
SGP_RELEASE_LABEL="Versão 2.0.1 RC2 • Homologação"

MAIL_MAILER=smtp
MAIL_HOST=HOST_DO_PROVEDOR
MAIL_PORT=587
MAIL_USERNAME=USUARIO_DO_PROVEDOR
MAIL_PASSWORD=SENHA_DO_PROVEDOR
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=ENDERECO_REMETENTE_VALIDADO
MAIL_FROM_NAME="SGP"
```

Os nomes exatos das variáveis de e-mail podem variar conforme o provedor. Use as credenciais fornecidas por ele e mantenha-as somente nas variáveis protegidas do ambiente.

Antes do deploy, confira duas vezes `APP_URL`. Ela deve apontar para a homologação, nunca para a produção.

## 7. Aplicação no Laravel Cloud

Após selecionar a branch e salvar as variáveis:

1. gere um snapshot ou backup do banco de homologação;
2. inicie o deploy da candidata;
3. confirme que a etapa de build executou `composer install` e `npm run build`;
4. execute no ambiente:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

5. consulte o log do deploy e confirme ausência de falhas;
6. abra a aplicação e verifique no rodapé: `Versão 2.0.1 RC2 • Homologação`.

A migration cria apenas `security_audit_events`. Ela não exclui nem transforma usuários, vínculos, organizações ou projetos.

## 8. Reteste obrigatório da MUD-HOM-SENHA-001

Execute e registre evidências para os cenários:

1. usuário ativo solicita recuperação no login e recebe o e-mail;
2. e-mail inexistente recebe a mesma mensagem pública, sem envio;
3. usuário inativo não recebe o link;
4. novo link invalida o anterior;
5. token expira após 60 minutos;
6. token funciona uma única vez;
7. nova senha permite login e a anterior deixa de funcionar;
8. Superadmin consegue reenviar o link;
9. Proprietário e Administrador conseguem reenviar para usuário da própria organização;
10. Membro e Leitor não conseguem reenviar;
11. vínculo de outra organização retorna negação sem exposição de dados;
12. token e URL não aparecem no HTML, sessão, auditoria ou interface;
13. o link aponta para o domínio exato da homologação;
14. nova conta recebe automaticamente o e-mail de primeiro acesso;
15. conta, perfil, vínculos e projetos são preservados após a redefinição.

Depois, faça regressão breve dos fluxos já aprovados: login, Administração da Plataforma, organizações, equipe, projetos, arquivos, auditoria e isolamento multiempresa.

## 9. Critérios para aprovação da candidata

A `v2.0.1-rc2` somente poderá ser declarada homologada quando:

- `php artisan test` estiver integralmente aprovado;
- o build do frontend estiver aprovado;
- todos os 15 cenários da mudança tiverem resultado satisfatório;
- não houver link, token ou senha exposto;
- o envio real de e-mail estiver confirmado;
- a regressão dos fluxos críticos estiver aprovada;
- as evidências estiverem arquivadas sem informações sensíveis.

## 10. Retorno seguro

Se o deploy falhar:

1. interrompa a homologação da candidata;
2. restaure o deploy anterior `v2.0.1-rc1`;
3. preserve a tabela `security_audit_events`, pois ela é isolada e não interfere no funcionamento anterior;
4. só reverta a migration se houver necessidade técnica comprovada e backup confirmado;
5. registre a ocorrência e não avance para a `main`.

## 11. Promoção para produção

Depois da homologação formal:

1. altere `SGP_RELEASE_LABEL` da produção para `Versão 2.0.1`;
2. abra a integração da branch `agent/v2.0.1-rc2` na `main`;
3. associe a release ao commit efetivamente homologado;
4. publique a nova produção em paralelo à antiga;
5. execute a verificação de fumaça e do envio de e-mails;
6. faça a troca de domínio somente depois da validação;
7. mantenha a produção anterior disponível para retorno até o encerramento da janela.

Não exclua a produção atual antes de a nova aplicação estar validada.
