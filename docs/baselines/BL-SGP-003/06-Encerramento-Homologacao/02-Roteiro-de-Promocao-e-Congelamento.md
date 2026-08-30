# Roteiro de Promoção e Congelamento

## Regra de segurança

Não mover tag histórica e não declarar SHA antes da implantação. O congelamento deve apontar para o commit que efetivamente passou pelos controles e foi publicado.

## 1. Aplicar a candidata P10

1. confirmar a branch feature/bl3-p09-comunicacao-acesso;
2. confirmar que o diretório contém somente alterações aprovadas da BL3;
3. fazer backup ou commit de segurança;
4. aplicar o pacote P10;
5. executar php artisan optimize:clear;
6. executar php artisan test;
7. executar git diff --check;
8. revisar git status --short.

## 2. Commit da candidata

~~~powershell
git add .
git commit -m "release: prepara SGP v3.0.0 e encerra BL-SGP-003"
git push origin feature/bl3-p09-comunicacao-acesso
~~~

Abrir ou atualizar o pull request para a branch main. Exigir checks aprovados e revisar migrations, configuração e arquivos novos.

## 3. Promoção à main

Depois da aprovação do pull request:

~~~powershell
git switch main
git pull --ff-only origin main
git log -1 --oneline
~~~

Registrar o SHA exibido. Não criar a tag antes de o deploy desse commit ser aprovado.

## 4. Laravel Cloud

1. confirmar que produção acompanha a branch main;
2. revisar variáveis protegidas, sem expor valores;
3. executar build e deploy;
4. confirmar migrations;
5. confirmar Managed Queue ou worker compatível com o driver adotado;
6. confirmar Object Storage privado;
7. executar a verificação funcional curta;
8. revisar logs sanitizados e jobs com falha.

## 5. Selo final

Depois da aprovação:

~~~powershell
git tag -a v3.0.0 -m "SGP v3.0.0 - BL-SGP-003 homologada"
git push origin v3.0.0
~~~

Preencher o termo de encerramento com SHA, data, URL, deploy e decisão. Regenerar o manifesto SHA-256. Publicar a release no repositório.

## Retorno

Se houver falha impeditiva, interromper a publicação, manter a tag sem criação e retornar à última release estável. Se a migration já tiver alterado produção, aplicar o plano de restauração do banco e do armazenamento do mesmo ponto lógico.
