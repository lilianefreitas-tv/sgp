# Documentação oficial do SGP MVP 1.0.0

Esta pasta contém os 14 documentos atualizados a partir da implementação
consolidada da pré-release.

## Organização

- Arquivos `.docx`: versões editáveis.
- Pasta `pdf`: versões de consulta renderizadas e verificadas.
- Documento 12: roteiro que deve ser preenchido durante a homologação final.
- Documento 14: registro da release candidata e condição para publicação.

## Regra de publicação

A tag `v1.0.0` deve ser criada somente depois de:

1. executar `php artisan test` e confirmar 93 testes aprovados;
2. executar os 35 casos do Documento 12 em PostgreSQL;
3. registrar evidências e pendências;
4. aprovar a homologação sem falha impeditiva.

Os identificadores existentes foram preservados. Backlog Consolidado,
Calendário, Gantt básico e identidade pública foram registrados como
`EV-MVP-001` a `EV-MVP-004`.
