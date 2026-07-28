# Changelog

## [1.0.0] - 2026-07-28

Primeira release estÃ¡vel e homologada do SGP.

### Adicionado

- identidade visual oficial, pÃ¡gina inicial e favicon do SGP;
- autenticaÃ§Ã£o e administraÃ§Ã£o de usuÃ¡rios;
- clientes, projetos, equipes e papÃ©is contextuais;
- requisitos versionados e tarefas com histÃ³rico;
- Kanban, calendÃ¡rio e Gantt bÃ¡sico;
- geraÃ§Ã£o versionada de DOCX e PDF;
- Backlog Consolidado do Projeto;
- comentÃ¡rios, anexos privados e histÃ³rico consolidado;
- dashboard com indicadores reais;
- roteiro integrado de homologaÃ§Ã£o final.

### Corrigido

- removida a exclusÃ£o fÃ­sica de conta pelo prÃ³prio usuÃ¡rio;
- `.env.example` alinhado ao SGP e ao PostgreSQL;
- removida a credencial previsÃ­vel do seeder e criado procedimento interativo
  para o primeiro administrador;
- sessÃµes autenticadas passam a ser encerradas imediatamente quando a conta Ã©
  desativada;
- licenÃ§a do pacote corrigida para identificar o SGP como software
  proprietÃ¡rio, removendo a declaraÃ§Ã£o MIT herdada do esqueleto Laravel;
- README, tecnologias, status e instruÃ§Ãµes de implantaÃ§Ã£o atualizados;
- referÃªncias herdadas do Laravel e do RotaMP removidas da experiÃªncia pÃºblica;
- artefatos temporÃ¡rios excluÃ­dos do pacote de release.

### Conhecido

- recuperaÃ§Ã£o pÃºblica de senha nÃ£o integra o MVP;
- 95 testes automatizados, com 337 asserÃ§Ãµes, usam SQLite em memÃ³ria;
- `HOM-001` a `HOM-034` foram aprovados em PostgreSQL;
- `HOM-035` depende da implantaÃ§Ã£o externa para validar HTTPS, webroot e
  permissÃµes do servidor;
- a compatibilidade com Object Storage estÃ¡ prevista para `v1.0.1`, antes do
  primeiro deploy em plataforma com sistema de arquivos efÃªmero;
- a FundaÃ§Ã£o SaaS Multiempresa e a ConfiguraÃ§Ã£o Adaptativa de Projetos estÃ£o
  aprovadas na `BL-SGP-002`, mas ainda nÃ£o foram implementadas;
- Wiki, Sprints, reuniÃµes, gestÃ£o interna de testes, contratos, baselines
  operacionais, mudanÃ§as, IA e Gantt avanÃ§ado permanecem como evoluÃ§Ã£o futura.
