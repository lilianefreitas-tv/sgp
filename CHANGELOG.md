# Changelog

## [1.0.0] - 2026-07-28

Primeira release estável e homologada do SGP.

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
- roteiro integrado de homologação final.

### Corrigido

- removida a exclusão física de conta pelo próprio usuário;
- `.env.example` alinhado ao SGP e ao PostgreSQL;
- removida a credencial previsível do seeder e criado procedimento interativo
  para o primeiro administrador;
- sessões autenticadas passam a ser encerradas imediatamente quando a conta é
  desativada;
- licença do pacote corrigida para identificar o SGP como software
  proprietário, removendo a declaração MIT herdada do esqueleto Laravel;
- README, tecnologias, status e instruções de implantação atualizados;
- referências herdadas do Laravel e do RotaMP removidas da experiência pública;
- artefatos temporários excluídos do pacote de release.

### Conhecido

- recuperação pública de senha não integra o MVP;
- 95 testes automatizados, com 337 asserções, usam SQLite em memória;
- `HOM-001` a `HOM-034` foram aprovados em PostgreSQL;
- `HOM-035` depende da implantação externa para validar HTTPS, webroot e
  permissões do servidor;
- a compatibilidade com Object Storage está prevista para `v1.0.1`, antes do
  primeiro deploy em plataforma com sistema de arquivos efêmero;
- a Fundação SaaS Multiempresa e a Configuração Adaptativa de Projetos estão
  aprovadas na `BL-SGP-002`, mas ainda não foram implementadas;
- Wiki, Sprints, reuniões, gestão interna de testes, contratos, baselines
  operacionais, mudanças, IA e Gantt avançado permanecem como evolução futura.
