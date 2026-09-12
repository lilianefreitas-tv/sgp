# Correção DEF-HOM-001

## Resultado esperado

Um usuário com membership organizacional ativo e papel ativo de `Gerente de Projetos` no projeto pode executar o fluxo documental do projeto sem receber perfil de Administrator da organização.

A correção cobre:

- criação, revisão e arquivamento de artefatos do projeto;
- atribuição de Autor, Revisor e Aprovador no projeto;
- publicação e revogação de revisão aprovada;
- constituição de baseline pelo papel ativo no projeto, mesmo quando `manager_id` não identifica esse usuário.

Artefatos de iniciativa continuam restritos a Owner, Administrator ou superadministrador com acesso temporário explícito. Membros comuns e papéis inativos continuam sem permissão.

## Aplicação na main

Copie o conteúdo deste pacote sobre a cópia local do repositório e execute:

```bash
git status
composer install
php artisan optimize:clear
php artisan test --filter='ArtifactRevisionTest|ArtifactWorkflowTest|ArtifactPublicationTest|ProjectBaselineTest'
git add app/Http/Controllers/ProjectBaselineController.php \
  app/Models/ArtifactPublication.php \
  app/Services/ArtifactRevisionService.php \
  app/Services/ArtifactWorkflowService.php \
  app/Services/ArtifactPublicationService.php \
  tests/Feature/ArtifactRevisionTest.php \
  tests/Feature/ArtifactWorkflowTest.php \
  tests/Feature/ArtifactPublicationTest.php \
  tests/Feature/ProjectBaselineTest.php \
  CORRECAO-DEF-HOM-001.md
git commit -m "fix: permite gerente de projeto no fluxo documental"
git push origin main
```

Não há migration nova.

## Reteste mínimo

1. Entrar como Camila.
2. Confirmar que Camila tem membership organizacional ativo e papel ativo `Gerente de Projetos` no projeto DEMO.
3. Criar o artefato do HOM-07-005.
4. Criar a baseline do HOM-07-006.
5. Atribuir Autor, Revisor e Aprovador no HOM-07-007.
6. Prosseguir até aprovação, publicação e revogação.
7. Registrar nova evidência do erro corrigido e marcar o DEF-HOM-001 como `Reteste aprovado` somente depois do fluxo completo.
