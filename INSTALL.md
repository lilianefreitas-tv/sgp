# Instalação e operação do SGP v1.0.1

## 1. Preparação

Instale PHP 8.2 ou superior, Composer 2, PostgreSQL 14 ou superior, Node.js 20
ou superior e npm. Habilite as extensões PHP exigidas pelo README.

Crie o banco e o usuário da aplicação no PostgreSQL. Em produção, use uma conta
própria, senha forte e apenas os privilégios necessários sobre o banco do SGP.
Em plataformas com filesystem efêmero, vincule também um Object Storage
compatível com S3 e mantenha o bucket privado.

## 2. Instalação local do zero

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
php artisan storage:unlink
php artisan serve
```

Edite o `.env` antes da migration:

```dotenv
APP_NAME=SGP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://endereco-do-sgp

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sgp
DB_USERNAME=sgp_app
DB_PASSWORD=troque_esta_senha

SGP_PRIVATE_DISK=local
```

Não execute `php artisan storage:link`: documentos e anexos são privados.

O comando do primeiro administrador solicita nome, e-mail e senha no terminal.
A senha não aparece na tela nem é aceita como argumento de linha de comando. Ela
deve ter pelo menos 12 caracteres, letras maiúsculas e minúsculas, número e
símbolo. O comando se recusa a criar outra conta quando já existe administrador
ativo.

## 3. Atualização de instalação existente

Faça backup do banco e do disco privado configurado antes de atualizar. Na
instalação local, copie `storage/app/private`. Em Object Storage, preserve uma
cópia consistente dos objetos.

```powershell
php artisan down
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan up
```

## 4. Testes

```powershell
php artisan test
```

Resultado de referência da release `v1.0.1`:

```text
Tests: 100 passed
Assertions: 358
```

Os casos `HOM-001` a `HOM-034` foram aprovados em PostgreSQL. Em novas
homologações, use um banco separado, nunca a produção.

## 5. Backup

Banco:

```powershell
pg_dump -Fc -h 127.0.0.1 -U sgp_app -d sgp -f sgp_backup.dump
```

Arquivos privados:

- disco `local`: copie integralmente `storage/app/private`;
- Object Storage: exporte ou replique os objetos do bucket privado.

Banco e arquivos precisam pertencer ao mesmo ponto lógico de recuperação.

## 6. Restauração

```powershell
createdb -h 127.0.0.1 -U postgres sgp_restaurado
pg_restore -h 127.0.0.1 -U postgres -d sgp_restaurado --clean --if-exists sgp_backup.dump
```

Restaure também o disco privado configurado, ajuste permissões ou credenciais e
valide os downloads de documentos e anexos.

A restauração da release `v1.0.0` foi validada em banco e diretório
alternativos, com login, consultas, documentos e anexos preservados.

## 7. Implantação no Laravel Cloud

A `v1.0.1` está preparada para filesystem efêmero. Os arquivos DOCX e PDF são
gerados temporariamente e enviados, junto com os anexos, ao disco privado
persistente.

### 7.1 Recursos

No ambiente de produção:

1. vincule um Laravel Serverless Postgres;
2. vincule um Laravel Object Storage com visibilidade `Private`;
3. use `s3` como nome do disco e defina-o como disco padrão;
4. mantenha aplicação, banco e bucket na mesma região, quando disponível.

O Laravel Cloud injeta as variáveis do banco, `FILESYSTEM_DISK` e as credenciais
compatíveis com S3. Não copie essas credenciais para o repositório.

### 7.2 Variáveis próprias

Gere uma chave exclusiva para produção:

```powershell
php artisan key:generate --show
```

Cadastre no ambiente:

```dotenv
APP_NAME=SGP
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:chave-gerada
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
LOG_LEVEL=warning

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SGP_PRIVATE_DISK=s3
SGP_ATTACHMENT_MAX_KB=10240
SGP_ATTACHMENT_EXTENSIONS=pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip
```

Defina `APP_URL` com o domínio fornecido pelo Laravel Cloud assim que ele estiver
disponível. Não sobrescreva manualmente as variáveis de banco e Object Storage
injetadas pela plataforma.

### 7.3 Build e deploy

Build:

```text
composer install --no-dev --prefer-dist --optimize-autoloader && npm ci && npm run build && php artisan optimize
```

Deploy:

```text
php artisan migrate --force
```

Não execute `php artisan storage:link` nem `php artisan optimize:clear` durante
o deploy.

### 7.4 Primeiro administrador

Para o bootstrap inicial, cadastre temporariamente no ambiente:

```dotenv
SGP_BOOTSTRAP_ADMIN_PASSWORD=uma-senha-forte-temporaria
```

Após o deploy, execute no console do Laravel Cloud:

```text
php artisan sgp:create-first-administrator --name="Liliane Freitas" --email="email-da-administradora" --no-interaction
```

Depois da criação:

1. confirme o login;
2. remova `SGP_BOOTSTRAP_ADMIN_PASSWORD` das variáveis do ambiente;
3. faça novo deploy para aplicar a remoção;
4. altere a senha dentro do SGP, se a senha temporária não for a definitiva.

Nunca passe a senha como argumento do comando nem a registre no GitHub.

### 7.5 Validação de persistência

Para concluir o `HOM-035`:

1. autentique-se com a primeira conta administradora;
2. cadastre um projeto;
3. envie e baixe um anexo;
4. gere e baixe os documentos DOCX e PDF;
5. faça novo deploy;
6. confirme que o anexo e os documentos continuam disponíveis;
7. valide HTTPS, `APP_DEBUG=false`, logs, permissões e restauração.

## 8. Checklist de produção

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS ativo;
- credenciais exclusivas para o banco;
- diretórios temporários e `bootstrap/cache` graváveis pela aplicação;
- tarefa de backup e teste periódico de restauração;
- logs fora da área pública e com rotação;
- servidor web apontando somente para `public`;
- fila e agendador configurados quando forem adotados;
- primeiro administrador criado por procedimento controlado;
- nenhuma credencial padrão ou previsível mantida no banco;
- arquivos permanentes no disco privado configurado;
- bucket de produção com visibilidade privada;
- variável `SGP_BOOTSTRAP_ADMIN_PASSWORD` removida após o bootstrap;
- `HOM-035` executado no ambiente real de produção.

A tag `v1.0.0` preserva o MVP homologado. A `v1.0.1` acrescenta a adaptação
operacional para nuvem, sem mudança de esquema de banco. O `HOM-035` permanece
como ressalva operacional até a implantação externa.
