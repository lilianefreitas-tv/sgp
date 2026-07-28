# Instalação e operação do SGP MVP 1.0.0

## 1. Preparação

Instale PHP 8.2 ou superior, Composer 2, PostgreSQL 14 ou superior, Node.js 20
ou superior e npm. Habilite as extensões PHP exigidas pelo README.

Crie o banco e o usuário da aplicação no PostgreSQL. Em produção, use uma conta
própria, senha forte e apenas os privilégios necessários sobre o banco do SGP.

## 2. Instalação do zero

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
```

Não execute `php artisan storage:link`: documentos e anexos são privados.

O comando do primeiro administrador solicita nome, e-mail e senha no terminal.
A senha não aparece na tela nem é aceita como argumento de linha de comando. Ela
deve ter pelo menos 12 caracteres, letras maiúsculas e minúsculas, número e
símbolo. O comando se recusa a criar outra conta quando já existe administrador
ativo.

## 3. Atualização de instalação existente

Faça backup do banco e de `storage/app/private` antes de atualizar.

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

Resultado de referência da release `v1.0.0`:

```text
Tests: 95 passed
Assertions: 337
```

Os casos `HOM-001` a `HOM-034` foram aprovados em PostgreSQL. Em novas
homologações, use um banco separado, nunca a produção.

## 5. Backup

Banco:

```powershell
pg_dump -Fc -h 127.0.0.1 -U sgp_app -d sgp -f sgp_backup.dump
```

Arquivos privados: copie integralmente `storage/app/private` para o mesmo
conjunto de backup. Banco e arquivos precisam pertencer ao mesmo ponto no
tempo.

## 6. Restauração

```powershell
createdb -h 127.0.0.1 -U postgres sgp_restaurado
pg_restore -h 127.0.0.1 -U postgres -d sgp_restaurado --clean --if-exists sgp_backup.dump
```

Restaure também `storage/app/private`, ajuste permissões e valide downloads de
documentos e anexos.

A restauração da release `v1.0.0` foi validada em banco e diretório
alternativos, com login, consultas, documentos e anexos preservados.

## 7. Armazenamento em plataformas de nuvem

A release `v1.0.0` utiliza `storage/app/private` em disco local persistente.
Antes de implantar em plataforma com sistema de arquivos efêmero, adapte o SGP
para utilizar um disco privado configurável e Object Storage.

Essa adaptação está prevista para a versão `v1.0.1` e deverá preservar:

- autorização de download pelo SGP;
- privacidade de documentos e anexos;
- geração temporária de DOCX e PDF;
- backup e restauração do PostgreSQL e do armazenamento de objetos;
- testes de documentos, anexos e isolamento.

## 8. Checklist de produção

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS ativo;
- credenciais exclusivas para o banco;
- diretórios `storage` e `bootstrap/cache` graváveis pela aplicação;
- tarefa de backup e teste periódico de restauração;
- logs fora da área pública e com rotação;
- servidor web apontando somente para `public`;
- fila e agendador configurados quando forem adotados;
- primeiro administrador criado por procedimento controlado;
- nenhuma credencial padrão ou previsível mantida no banco;
- arquivos privados fora da pasta pública;
- `HOM-035` executado no ambiente real de produção.

A tag `v1.0.0` já foi publicada após a aprovação dos casos `HOM-001` a
`HOM-034`. O `HOM-035` permanece como ressalva operacional até a implantação
externa.
