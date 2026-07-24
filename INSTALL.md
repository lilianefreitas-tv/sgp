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

Resultado esperado: `93 passed`.

Para a homologação PostgreSQL, use um banco separado, nunca a produção.

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

## 7. Checklist de produção

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
- homologação final aprovada antes da tag `v1.0.0`.
