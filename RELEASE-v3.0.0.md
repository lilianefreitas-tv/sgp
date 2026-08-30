# SGP v3.0.0

Release da **BL-SGP-003 - Engenharia Documental Adaptativa**.

## Situação

- homologação funcional: aprovada em 30/08/2026;
- testes automatizados: 367 aprovados, com 1.473 asserções;
- candidata final: preparada pelo P10;
- commit publicado: a registrar depois do merge e do deploy;
- tag: criar somente no commit efetivamente implantado.

## Conteúdo

- iniciativas, aplicabilidade e jornada comercial;
- artefatos estruturados e publicações;
- integração controlada com projetos;
- contratos e baselines de projeto;
- mudanças e análise de impacto;
- testes, evidências e rastreabilidade;
- SMTP, recuperação de senha e senha temporária;
- auditoria de segurança da plataforma.

## Fora do escopo

A reformulação da identidade visual e demais evoluções comerciais ou funcionais dependem de análise e aprovação para a **BL-SGP-004**.

## Critério de publicação

A release somente será declarada congelada depois de:

1. suíte integral e build aprovados em cópia limpa;
2. merge controlado na branch main;
3. deploy e migrations aprovados no Laravel Cloud;
4. fila, SMTP, persistência e logs validados;
5. SHA do commit e evidências registrados;
6. tag v3.0.0 criada no commit implantado;
7. manifesto final regenerado.
