# Rotina Git do SGP

## Estado de fechamento

~~~text
Branch estável: main
Release candidata: v3.0.0
Baseline: BL-SGP-003
Homologação funcional: P01 a P09 aprovados
Commit e tag: registrar somente após merge e deploy
~~~

## Validar a candidata

Na branch feature/bl3-p09-comunicacao-acesso:

~~~powershell
Set-Location C:\Projetos\sgp
php artisan optimize:clear
php artisan test
npm run build
git diff --check
git status --short
~~~

## Registrar o P10

~~~powershell
git add .
git diff --cached
git commit -m "release: prepara SGP v3.0.0 e encerra BL-SGP-003"
git push origin feature/bl3-p09-comunicacao-acesso
~~~

Abra ou atualize o pull request para a main. Só promova com checks aprovados e revisão concluída.

## Depois do merge

~~~powershell
git switch main
git pull --ff-only origin main
git log -1 --oneline
~~~

Registre o SHA, mas ainda não crie a tag. Primeiro valide o deploy desse commit no Laravel Cloud, incluindo migrations, fila, SMTP, persistência, logs e smoke test.

## Selar a release

Somente depois do deploy aprovado:

~~~powershell
git tag -a v3.0.0 -m "SGP v3.0.0 - BL-SGP-003 homologada"
git push origin v3.0.0
~~~

Complete o termo de encerramento, registre a URL da release e regenere o manifesto SHA-256.

## Próxima etapa

Antes de programar a BL-SGP-004, registre e analise as necessidades comerciais, jurídicas, tributárias, contratuais, de licenciamento, suporte, privacidade e operação. A reformulação da identidade visual também pertence a esse planejamento futuro.

Nunca versione arquivos de ambiente, bancos, logs, anexos, credenciais, vendor ou node_modules. Nunca mova uma tag histórica.
