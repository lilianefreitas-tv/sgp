# Rotina Git do SGP

## Estado oficial

```text
Branch estÃ¡vel: main
Release: v1.0.0
Commit da release: a5bc837
HomologaÃ§Ã£o: HOM-001 a HOM-034 aprovados
Ressalva operacional: HOM-035 no ambiente real de produÃ§Ã£o
```

## Antes de comeÃ§ar

```powershell
Set-Location C:\Projetos\sgp
git status
git switch main
git pull --ff-only origin main
```

## Registrar uma alteraÃ§Ã£o

```powershell
git status
git add .
git commit -m "tipo: descriÃ§Ã£o objetiva da alteraÃ§Ã£o"
git push
```

Tipos sugeridos: `feat`, `fix`, `docs`, `test`, `refactor`, `chore` e
`release`.

## Registrar a documentaÃ§Ã£o pÃ³s-homologaÃ§Ã£o

Depois de substituir os documentos revisados e conferir a estrutura de
`docs`, execute:

```powershell
git status
git add README.md INSTALL.md CHANGELOG.md docs
git diff --cached
git commit -m "docs: atualiza registros pÃ³s-homologaÃ§Ã£o da v1.0.0"
git push origin main
```

NÃ£o recrie nem mova a tag `v1.0.0`. Ela identifica corretamente o commit
homologado `a5bc837`. O commit documental posterior ficarÃ¡ na `main`, sem alterar
o conteÃºdo histÃ³rico da tag.

## PrÃ³ximas branches

A adaptaÃ§Ã£o para armazenamento privado em nuvem deverÃ¡ ocorrer em branch
prÃ³pria, criada a partir da `main` atualizada. Depois da publicaÃ§Ã£o e validaÃ§Ã£o
da versÃ£o `v1.0.1`, o desenvolvimento da `BL-SGP-002` deverÃ¡ comeÃ§ar em outra
branch.

NÃ£o misture a correÃ§Ã£o operacional de nuvem com a FundaÃ§Ã£o SaaS Multiempresa.

Nunca versione `.env`, bancos locais, logs, anexos de teste, documentos gerados,
`vendor` ou `node_modules`.
