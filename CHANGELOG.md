# Changelog

## [1.0.0] - 2026-07-23

### Adicionado

- identidade visual oficial, página inicial e favicon do SGP;
- autenticação e administração de usuários;
- clientes, projetos, equipes e papéis contextuais;
- requisitos versionados e tarefas com histórico;
- Kanban, calendário e Gantt básico;
- geração versionada de DOCX e PDF;
- Backlog Consolidado do Projeto;
- comentários, anexos privados e histórico consolidado;
- dashboard com indicadores reais;
- documentação técnica e roteiro de homologação final.

### Corrigido

- removida a exclusão física de conta pelo próprio usuário;
- `.env.example` alinhado ao SGP e ao PostgreSQL;
- README, tecnologias, status e instruções de implantação atualizados;
- referências herdadas do Laravel e do RotaMP removidas da experiência pública;
- artefatos temporários excluídos do pacote de release.

### Conhecido

- recuperação pública de senha não integra o MVP;
- testes automatizados usam SQLite em memória; PostgreSQL é validado no roteiro
  manual de homologação;
- Wiki, Sprints, reuniões, testes internos, riscos, mudanças e Gantt avançado
  permanecem no backlog de evolução.
