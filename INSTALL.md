# InstalaÃ§Ã£o e operaÃ§Ã£o do SGP MVP 1.0.0

## 1. PreparaÃ§Ã£o

Instale PHP 8.2 ou superior, Composer 2, PostgreSQL 14 ou superior, Node.js 20
ou superior e npm. Habilite as extensÃµes PHP exigidas pelo README.

Crie o banco e o usuÃ¡rio da aplicaÃ§Ã£o no PostgreSQL. Em produÃ§Ã£o, use uma conta
prÃ³pria, senha forte e apenas os privilÃ©gios necessÃ¡rios sobre o banco do SGP.

## 2. InstalaÃ§Ã£o do zero

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

NÃ£o execute `php artisan storage:link`: documentos e anexos sÃ£o privados.

O comando do primeiro administrador solicita nome, e-mail e senha no terminal.
A senha nÃ£o aparece na tela nem Ã© aceita como argumento de linha de comando. Ela
deve ter pelo menos 12 caracteres, letras maiÃºsculas e minÃºsculas, nÃºmero e
sÃ­mbolo. O comando se recusa a criar outra conta quando jÃ¡ existe administrador
ativo.

## 3. AtualizaÃ§Ã£o de instalaÃ§Ã£o existente

FaÃ§a backup do banco e de `storage/app/private` antes de atualizar.

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

Resultado de referÃªncia da release `v1.0.0`:

```text
Tests: 95 passed
Assertions: 337
```

Os casos `HOM-001` a `HOM-034` foram aprovados em PostgreSQL. Em novas
homologaÃ§Ãµes, use um banco separado, nunca a produÃ§Ã£o.

## 5. Backup

Banco:

```powershell
pg_dump -Fc -h 127.0.0.1 -U sgp_app -d sgp -f sgp_backup.dump
```

Arquivos privados: copie integralmente `storage/app/private` para o mesmo
conjunto de backup. Banco e arquivos precisam pertencer ao mesmo ponto no
tempo.

## 6. RestauraÃ§Ã£o

```powershell
createdb -h 127.0.0.1 -U postgres sgp_restaurado
pg_restore -h 127.0.0.1 -U postgres -d sgp_restaurado --clean --if-exists sgp_backup.dump
```

Restaure tambÃ©m `storage/app/private`, ajuste permissÃµes e valide downloads de
documentos e anexos.

A restauraÃ§Ã£o da release `v1.0.0` foi validada em banco e diretÃ³rio
alternativos, com login, consultas, documentos e anexos preservados.

## 7. Armazenamento em plataformas de nuvem

A release `v1.0.0` utiliza `storage/app/private` em disco local persistente.
Antes de implantar em plataforma com sistema de arquivos efÃªmero, adapte o SGP
para utilizar um disco privado configurÃ¡vel e Object Storage.

Essa adaptaÃ§Ã£o estÃ¡ prevista para a versÃ£o `v1.0.1` e deverÃ¡ preservar:

- autorizaÃ§Ã£o de download pelo SGP;
- privacidade de documentos e anexos;
- geraÃ§Ã£o temporÃ¡ria de DOCX e PDF;
- backup e restauraÃ§Ã£o do PostgreSQL e do armazenamento de objetos;
- testes de documentos, anexos e isolamento.

## 8. Checklist de produÃ§Ã£o

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS ativo;
- credenciais exclusivas para o banco;
- diretÃ³rios `storage` e `bootstrap/cache` gravÃ¡veis pela aplicaÃ§Ã£o;
- tarefa de backup e teste periÃ³dico de restauraÃ§Ã£o;
- logs fora da Ã¡rea pÃºblica e com rotaÃ§Ã£o;
- servidor web apontando somente para `public`;
- fila e agendador configurados quando forem adotados;
- primeiro administrador criado por procedimento controlado;
- nenhuma credencial padrÃ£o ou previsÃ­vel mantida no banco;
- arquivos privados fora da pasta pÃºblica;
- `HOM-035` executado no ambiente real de produÃ§Ã£o.

A tag `v1.0.0` jÃ¡ foi publicada apÃ³s a aprovaÃ§Ã£o dos casos `HOM-001` a
`HOM-034`. O `HOM-035` permanece como ressalva operacional atÃ© a implantaÃ§Ã£o
externa.
