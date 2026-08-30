# Instalação, atualização e operação do SGP v3.0.0

Este manual descreve a candidata final da `BL-SGP-003`. Não inclua segredos reais, arquivos `.env`, dumps, tokens ou chaves no repositório ou nas evidências.

## 1. Requisitos

- PHP 8.2 ou superior e extensões exigidas pelo Composer;
- Composer 2;
- PostgreSQL 14 ou superior;
- Node.js 20 ou superior e npm;
- Git;
- armazenamento privado persistente, local ou compatível com S3;
- serviço SMTP transacional com domínio remetente validado;
- processo permanente para a fila Laravel quando `QUEUE_CONNECTION` não for `sync`.

## 2. Instalação local

```powershell
Set-Location C:\Projetos\sgp
Copy-Item .env.example .env
composer install --no-interaction
php artisan key:generate
npm ci
npm run build
php artisan migrate --seed
php artisan sgp:create-first-administrator
php artisan optimize:clear
php artisan serve
```

Configure o PostgreSQL antes da migration. Use usuário próprio da aplicação e privilégios mínimos. Não execute `php artisan storage:link`: documentos e anexos permanentes são privados.

## 3. Configuração por ambiente

### Aplicação

```dotenv
APP_NAME=SGP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://endereco-oficial-do-sgp
APP_KEY=base64:chave-exclusiva-do-ambiente
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
SGP_RELEASE_LABEL="Versão 3.0.0"
```

Gere uma chave exclusiva com `php artisan key:generate --show`. Não reutilize a chave local em produção.

### Banco, sessão, cache e fila

```dotenv
DB_CONNECTION=pgsql
DB_HOST=host-protegido
DB_PORT=5432
DB_DATABASE=sgp
DB_USERNAME=sgp_app
DB_PASSWORD=segredo-protegido
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### Armazenamento privado

Para instalação persistente local:

```dotenv
FILESYSTEM_DISK=local
SGP_PRIVATE_DISK=local
```

Para plataforma com filesystem efêmero, use Object Storage privado e configure `SGP_PRIVATE_DISK=s3`. Preserve banco e objetos no mesmo ponto lógico de backup.

### Comunicação transacional

Exemplo para SMTP compatível com Resend:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=chave-protegida-do-provedor
MAIL_FROM_ADDRESS=nao-responda@dominio-verificado
MAIL_FROM_NAME="PRISMA SGP"
MAIL_EHLO_DOMAIN=dominio-verificado
```

No provedor de DNS, publique os registros solicitados pelo serviço transacional e aguarde a validação de DKIM e SPF. Configure DMARC conforme a política da organização. Nunca cole a chave SMTP em formulário, captura, chamado ou arquivo versionado.

Depois de alterar variáveis:

```powershell
php artisan optimize:clear
```

Valide a configuração na área `Administração da plataforma > Comunicação e SMTP` e envie uma mensagem para endereço controlado. Confirme aceitação pelo provedor e recebimento real.

## 4. Fila e tarefas em segundo plano

Em desenvolvimento:

```powershell
php artisan queue:work --tries=3 --timeout=90
```

No Laravel Cloud, a opção recomendada para produção é uma **Managed Queue**, que provisiona e escala workers dedicados e apresenta jobs com falha no painel. Se a implantação mantiver `QUEUE_CONNECTION=database`, use um worker cluster ou processo de fundo com o mesmo comando. Depois de cada deploy, reinicie workers autogerenciados para carregar o código e a configuração atuais:

```text
php artisan queue:restart
```

Monitore a tabela de jobs com falha e os logs sanitizados. O teste de entrega da tela de SMTP é imediato; redefinições comuns continuam usando a fila configurada.

## 5. Atualização de instalação existente

Antes de atualizar, faça backup consistente do PostgreSQL e do armazenamento privado e valide a possibilidade de restauração.

```powershell
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan up
php artisan queue:restart
```

Não execute rollback destrutivo em produção sem plano e backup compatíveis. O retorno recomendado é restaurar o banco e os arquivos privados do mesmo ponto e implantar novamente a última tag estável.

## 6. Validação antes da publicação

```powershell
php artisan optimize:clear
php artisan test
git diff --check
```

Referência da candidata final:

```text
Tests: 367 passed
Assertions: 1473
```

Execute ainda:

1. login e seleção de organização;
2. permissões de Superadmin e conta comum;
3. criação e consulta de registros críticos;
4. upload e download privado;
5. geração DOCX e PDF;
6. envio SMTP real;
7. redefinição pública de senha;
8. senha temporária e troca obrigatória;
9. auditoria da plataforma e auditoria organizacional;
10. persistência após novo deploy.

## 7. Laravel Cloud

### Recursos

No ambiente de produção:

1. vincule PostgreSQL gerenciado;
2. vincule Object Storage privado quando o filesystem for efêmero;
3. mantenha aplicação, banco e bucket na mesma região quando possível;
4. cadastre as variáveis protegidas sem sobrescrever credenciais injetadas pela plataforma;
5. prefira uma Managed Queue; se mantiver o driver `database`, configure worker cluster ou processo de fundo permanente;
6. associe o domínio oficial e valide HTTPS.

### Build

```text
composer install --no-dev --prefer-dist --optimize-autoloader && npm ci && npm run build && php artisan optimize
```

### Deploy

```text
php artisan migrate --force
```

O Laravel Cloud pode disparar automaticamente um deploy após o push da branch configurada e executa as etapas de build e deploy antes da troca sem indisponibilidade. Após a publicação, confirme `APP_DEBUG=false`, `SGP_RELEASE_LABEL="Versão 3.0.0"`, migrations concluídas, fila ativa, SMTP operacional, armazenamento persistente e ausência de erro nos logs.

## 8. Primeiro administrador

Para bootstrap não interativo, cadastre temporariamente `SGP_BOOTSTRAP_ADMIN_PASSWORD` nas variáveis protegidas e execute:

```text
php artisan sgp:create-first-administrator --name="Nome da administradora" --email="email-controlado" --no-interaction
```

Remova a variável imediatamente depois, faça novo deploy e valide o login. Nunca forneça a senha como argumento ou a registre em evidência.

## 9. Backup e restauração

Banco:

```powershell
pg_dump -Fc -h host -U sgp_app -d sgp -f sgp_backup.dump
```

Restauração em ambiente alternativo:

```powershell
createdb -h host -U postgres sgp_restaurado
pg_restore -h host -U postgres -d sgp_restaurado --clean --if-exists sgp_backup.dump
```

Restaure também o disco privado correspondente. Valide autenticação, contagens, relacionamentos, anexos, documentos e logs antes de considerar o ensaio aprovado.

## 10. Checklist de congelamento

- [ ] backup e restauração verificados;
- [ ] dependências e build aprovados;
- [ ] migrations executadas sem erro;
- [ ] 367 testes e 1.473 asserções aprovados;
- [ ] validação funcional P01 a P09 concluída;
- [ ] worker da fila ativo;
- [ ] SMTP, redefinição e senha temporária aprovados;
- [ ] Object Storage privado e persistência aprovados;
- [ ] logs sem erro impeditivo e sem segredos;
- [ ] commit implantado registrado;
- [ ] tag `v3.0.0` criada no commit implantado;
- [ ] release do GitHub publicada;
- [ ] manifesto SHA-256 atualizado;
- [ ] termo final assinado pela responsável.
