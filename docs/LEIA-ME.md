# Documentação oficial pós-homologação do SGP

Data da revisão: 28/07/2026

## Fonte vigente

- `BL-SGP-001_MVP-1.0.0`: retrato oficial do MVP implementado, homologado e publicado como `v1.0.0`.
- `BL-SGP-002_Fundacao-SaaS`: escopo aprovado para a próxima implementação. O software correspondente ainda não foi implementado.
- `00_Governanca_Documental`: catálogo mestre e relatório de conciliação.

## Registro da release

- Branch estável: `main`.
- Commit oficial: `a5bc837`.
- Tag publicada: `v1.0.0`.
- Testes automatizados: 95 aprovados.
- Asserções: 337 aprovadas.
- Homologação: `HOM-001` a `HOM-034` aprovados.
- Ressalva operacional: revalidar o `HOM-035` no servidor real de produção.

## Revisões documentais

- A `BL-SGP-001` foi atualizada para a revisão documental `1.0.2` somente nos itens `00`, `01`, `05`, `08`, `09`, `12`, `13`, `14` e `15`.
- Os itens `02`, `03`, `04`, `06`, `07`, `10` e `11` permanecem na revisão `1.0.1`, pois não houve mudança funcional.
- A `BL-SGP-002` permanece integralmente na revisão `2.1`, aprovada para implementação e ainda não homologada.
- O Relatório de Conciliação de 27/07/2026 foi preservado como registro histórico da análise que originou o pacote definitivo.

## Regra

Não misture documentos avulsos ou pacotes anteriores com este conjunto. Mudanças futuras devem gerar registro de mudança, revisão identificada e nova baseline quando alterarem escopo ou concepção.

## Próxima etapa

A adaptação do armazenamento privado para uma plataforma com filesystem efêmero está prevista como correção operacional `v1.0.1`, ainda vinculada à `BL-SGP-001`. A implementação da `BL-SGP-002` deverá ocorrer em branch própria e não pode alterar retroativamente a tag `v1.0.0`.

## Materiais não incluídos

O arquivo `sgp.pdf` permanece histórico e conceitual. Backups de código, banco, guias do MPS.BR e planilhas externas não integram esta documentação autoral.
