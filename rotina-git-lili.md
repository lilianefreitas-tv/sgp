# Rotina Git do SGP

## Antes de começar

```powershell
Set-Location C:\Projetos\sgp
git status
git pull --ff-only
```

## Registrar uma alteração

```powershell
git status
git add .
git commit -m "tipo: descrição objetiva da alteração"
git push
```

Tipos sugeridos: `feat`, `fix`, `docs`, `test`, `refactor`, `chore` e
`release`.

## Criar a release MVP 1.0.0

Execute somente depois da homologação final aprovada:

```powershell
git status
git add .
git commit -m "release: publica MVP 1.0.0"
git tag -a v1.0.0 -m "SGP MVP 1.0.0"
git push
git push origin v1.0.0
```

Nunca versione `.env`, bancos locais, logs, anexos de teste, documentos gerados,
`vendor` ou `node_modules`.
