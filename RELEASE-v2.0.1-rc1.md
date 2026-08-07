# SGP v2.0.1-rc1 | Transição controlada da produção

## Situação

Esta candidata corrige o procedimento de implantação da `v2.0.0` no banco legado
de produção. A baseline `BL-SGP-002` e a tag histórica `v2.0.0` permanecem
inalteradas. A candidata ainda não está homologada nem autorizada para deploy.

## Origem e inventário aprovado

- decisão registrada em 04/08/2026;
- PostgreSQL Serverless 18 com retenção de backup de sete dias;
- uma usuária administrativa, ID `1`;
- três clientes e quatro projetos;
- projetos `1`, `2` e `3` destinados à organização `mppa`;
- projeto `4` destinado à organização `sgp`;
- renumeração aprovada de `PRJ-0004` para `sgp/PRJ-0001`;
- quantitativos completos preservados na migration e no teste de transição.

## Conteúdo técnico

1. `2026_08_03_150000_transition_production_organizations.php`
   valida o inventário, bloqueia gravações concorrentes no PostgreSQL durante a
   transação, cria as organizações e distribui os dados antes das restrições da F4.
2. Os quatro modelos documentais legados são preservados no MPPA e clonados para
   o SGP com códigos temporários, pois a unicidade ainda é global nesse ponto.
   `2026_08_03_250000_finalize_production_template_codes.php` normaliza os códigos
   depois que a unicidade passa a ser organizacional. Documentos do projeto `4`
   apontam para os clones da organização correta.
3. `sgp:verify-production-transition` produz relatório JSON somente de leitura
   após todas as migrations e interrompe a validação diante de qualquer diferença.
4. `ProductionOrganizationTransitionTest` cobre o inventário real, a rejeição de
   divergências com rollback e a compatibilidade com instalação limpa.
5. `sgp:import-selective-project-data` oferece a rota alternativa aprovada para
   um banco novo: valida os seis CSVs e o manifesto SHA-256, simula por padrão e
   somente grava, em uma transação, quando chamado com `--apply`.
6. `SelectiveProjectDataImportTest` cobre a simulação sem gravação, a importação
   com remapeamento de organização e usuário, CSV adulterado e destino ocupado.
7. `.github/workflows/ci.yml` instala PHP e Node.js, compila os ativos e executa
   a suíte Laravel automaticamente em cada pull request.
8. `.github/workflows/isolated-rehearsal.yml` executa migrations, testes críticos
   e regressão completa em PostgreSQL 18 descartável. O executor protegido recusa
   bancos fora do padrão `sgp_rehearsal` e produz evidências técnicas sem segredos.

## Invariantes

- nenhum ID legado é recriado ou alterado;
- nenhuma linha de negócio pode permanecer sem `organization_id`;
- relações pai e filha não podem cruzar organizações;
- a migration somente aceita o inventário aprovado;
- uma instalação nova, sem dados de negócio, segue o bootstrap genérico da
  `v2.0.0`;
- `migrate:rollback` não é estratégia de retorno desta transição.

## Retorno

Em caso de falha antes da conclusão da migration, a transação é desfeita. Depois
da conclusão, o retorno autorizado é a restauração do PostgreSQL pelo ponto de
recuperação criado antes do deploy. Os arquivos de origem no Object Storage devem
ser preservados durante a reconciliação.

## Critério para avançar

A candidata somente poderá seguir para ensaio em banco restaurado depois de:

- análise estática sem erros;
- teste automatizado direcionado aprovado;
- suíte completa aprovada;
- revisão da sequência de deploy e de reconciliação dos 12 arquivos pertencentes
  aos seis documentos legados.

O ensaio isolado adicional está preparado, mas permanece não executado até que
seu workflow seja publicado na branch do pull request. Um resultado verde nesse
job constitui evidência técnica e não substitui a execução manual dos casos F8
nem a decisão formal de homologação.
