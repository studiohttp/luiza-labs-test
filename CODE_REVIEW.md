# Code review — Desafio LuizaLabs

Revisão confrontada com o PDF oficial e com `data_1.txt` e `data_2.txt`.

## Divergências confirmadas e corrigidas

- Layout fixo de 95 bytes e validação rigorosa de IDs, moeda e data.
- Normalização de valores com uma ou duas casas decimais encontrados nos dados oficiais.
- Contrato de saída `usuário → pedidos → produtos`, sem campos internos do MongoDB.
- Produtos não são deduplicados por `product_id`: esse ID se repete com valores diferentes nos exemplos oficiais.
- Upload passou a ser assíncrono, com Redis, worker, retry, backoff e limpeza após sucesso.
- Processamento deixou de acumular o arquivo em memória e passou a usar staging SQLite em disco e lotes configuráveis.
- Persistência idempotente por substituição determinística de cada pedido via `upsert`.
- Índices MongoDB automatizados para pedido, usuário e data.
- Validação de upload, intervalo de datas e respostas públicas sem detalhes internos.
- README e ambiente Docker alinhados ao comportamento real.

## Sugestões não implementadas

- Endpoint de status da importação: útil, mas não exigido pelo desafio e aumentaria o escopo.
- Modelagem de um documento MongoDB por usuário: documentos por pedido simplificam filtros e escrita em lote; o agrupamento por usuário ocorre na borda da API.
- Fallback em arquivo para escrita: não é usado porque poderia criar duas fontes de verdade. Em indisponibilidade do MongoDB, o job é repetido.

## Trade-off explícito

Um novo arquivo que contenha apenas parte de um pedido existente substitui esse pedido. A estratégia favorece idempotência simples e previsível para a reimportação do mesmo arquivo, sem assumir unicidade de produto.
