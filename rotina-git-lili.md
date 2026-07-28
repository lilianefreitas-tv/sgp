# Rotina Git do SGP

## Estado oficial

```text
Branch estável: main
Release: v1.0.0
Commit da release: a5bc837
Homologação: HOM-001 a HOM-034 aprovados
Ressalva operacional: HOM-035 no ambiente real de produção
```

## Antes de começar

```powershell
Set-Location C:\Projetos\sgp
git status
git switch main
git pull --ff-only origin main
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

## Registrar a documentação pós-homologação

Depois de substituir os documentos revisados e conferir a estrutura de
`docs`, execute:

```powershell
git status
git add README.md INSTALL.md CHANGELOG.md docs
git diff --cached
git commit -m "docs: atualiza registros pós-homologação da v1.0.0"
git push origin main
```

Não recrie nem mova a tag `v1.0.0`. Ela identifica corretamente o commit
homologado `a5bc837`. O commit documental posterior ficará na `main`, sem alterar
o conteúdo histórico da tag.

## Próximas branches

A adaptação para armazenamento privado em nuvem deverá ocorrer em branch
própria, criada a partir da `main` atualizada. Depois da publicação e validação
da versão `v1.0.1`, o desenvolvimento da `BL-SGP-002` deverá começar em outra
branch.

Não misture a correção operacional de nuvem com a Fundação SaaS Multiempresa.

Nunca versione `.env`, bancos locais, logs, anexos de teste, documentos gerados,
`vendor` ou `node_modules`.
