# Ensaio isolado da candidata SGP v2.0.1-rc1

## Situação

Este roteiro operacional está preparado, mas ainda não executado. Ele parte do
commit `2fd541da4b8f8f6fa8c78193190e920457ff59fb`, aprovado na CI com SQLite, e
acrescenta uma segunda verificação em PostgreSQL 18 descartável.

O ensaio não faz merge, deploy, importação real ou acesso ao banco de produção.
Seu resultado constitui evidência técnica para a homologação, mas não declara a
`BL-SGP-002` homologada.

## Isolamento aplicado

- banco PostgreSQL 18 criado como serviço efêmero do GitHub Actions;
- usuário e senha exclusivos e sem valor fora da execução;
- armazenamento local e descartável;
- nenhuma variável secreta ou credencial de produção;
- massa sintética criada pelos testes automatizados;
- descarte automático do banco ao terminar o job;
- permissão do workflow limitada à leitura do repositório.

O executor recusa a operação destrutiva `migrate:fresh` salvo quando todas as
condições abaixo forem verdadeiras:

1. `SGP_ALLOW_ISOLATED_RESET=YES`;
2. `APP_ENV=testing`;
3. `DB_CONNECTION=pgsql`;
4. `DB_HOST` local ou pertencente ao serviço descartável;
5. nome do banco iniciado por `sgp_rehearsal`;
6. discos público e privado configurados como `local`.

## Cobertura

O ensaio executa, em PostgreSQL:

1. migrations completas em banco vazio;
2. inventário e transição organizacional da produção simulada;
3. importação seletiva em simulação e em aplicação transacional;
4. rejeição de CSV adulterado e de banco de destino ocupado;
5. contagens, IDs, relações, organizações e remapeamentos;
6. integridade, nulabilidade, chaves estrangeiras e ausência de vínculos cruzados;
7. isolamento de contexto, arquivos e auditoria;
8. configuração adaptativa de projetos;
9. suíte Laravel completa para regressão.

Essa cobertura sustenta os casos automatizados de `CT-TEN`, `CT-MIG`, `CT-CFG`
e `CT-REG`. Os casos marcados como manuais no pacote F8 continuam pendentes até
execução humana no ambiente de homologação.

## Evidências produzidas

O workflow publica por 14 dias o artefato
`isolated-rehearsal-<commit>`, contendo:

- identificação sanitizada do ambiente e do commit;
- saída das migrations e seu estado final;
- resultado dos testes críticos em texto e JUnit XML;
- resultado da suíte completa em texto e JUnit XML;
- estado final do Git;
- decisão técnica da execução;
- manifesto SHA-256 das evidências.

Nenhum `.env`, senha, token, dado pessoal ou arquivo de produção integra o
artefato.

## Critério para avançar

O ensaio é tecnicamente aprovado somente com código de saída zero em todas as
etapas. Qualquer falha de isolamento, perda de integridade, divergência de
contagem, defeito crítico ou alto mantém a implantação bloqueada.

Mesmo com o ensaio aprovado, ainda será necessário:

1. registrar as evidências na matriz F8;
2. executar os casos manuais aplicáveis;
3. ensaiar a cópia controlada dos dados e a reconciliação dos 12 arquivos;
4. revisar o retorno por restauração do ponto de recuperação;
5. formalizar a decisão de homologação antes de merge ou deploy.

## Acionamento

O workflow `.github/workflows/isolated-rehearsal.yml` inicia automaticamente
quando esta alteração chega ao pull request. Depois de integrado à branch
principal, também poderá ser iniciado manualmente pela interface do GitHub.
