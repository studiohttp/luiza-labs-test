# Desafio LuizaLabs — Vertical Logística

API REST em Laravel para receber arquivos legados de pedidos, validar o layout de largura fixa e disponibilizar os dados agrupados por usuário, pedido e produto.

## Stack

- PHP 8.4 e Laravel 12
- MongoDB 7 para persistência e consultas
- Redis 7 e Laravel Queue para processamento assíncrono
- SQLite como staging temporário em disco
- Docker e Docker Compose

## Como executar

Requisitos: Docker e Docker Compose.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan test
```

A API fica disponível em `http://localhost:8000`.

## Importação

```bash
curl -X POST \
  -F "file=@docs-desafio/data_1.txt" \
  http://localhost:8000/api/orders/import
```

O endpoint armazena o arquivo com um UUID, envia um job ao Redis e responde com HTTP `202`:

```json
{
  "status": "queued",
  "file_name": "data_1.txt",
  "message": "Arquivo recebido e enfileirado para processamento."
}
```

O worker remove o arquivo temporário somente depois do processamento bem-sucedido. Em falha, o job é repetido com backoff e o arquivo é preservado para diagnóstico.

## Consulta

```bash
curl "http://localhost:8000/api/orders"
curl "http://localhost:8000/api/orders?order_id=753"
curl "http://localhost:8000/api/orders?date_start=2021-01-01"
curl "http://localhost:8000/api/orders?date_end=2021-12-31"
curl "http://localhost:8000/api/orders?date_start=2021-01-01&date_end=2021-12-31"
curl "http://localhost:8000/api/orders/753"
```

Os filtros podem ser combinados. Um intervalo com data final anterior à inicial retorna HTTP `422`.

## Contrato de resposta

`GET /api/orders` retorna diretamente uma lista no contrato do desafio:

```json
[
  {
    "user_id": 70,
    "name": "Palmer Prosacco",
    "orders": [
      {
        "order_id": 753,
        "date": "2021-03-08",
        "total": "1836.74",
        "products": [
          {
            "product_id": 3,
            "value": "1836.74"
          }
        ]
      }
    ]
  }
]
```

Detalhes internos do MongoDB, como `_id`, não são expostos.

## Arquitetura e uso de memória

O request HTTP não processa o conteúdo. Ele salva o upload em volume persistente compartilhado e despacha um job para o worker.

O worker executa duas fases:

1. Lê com `fgets()`, valida cada linha e grava apenas linhas válidas em um staging SQLite temporário.
2. Percorre o staging ordenado por pedido, mantém somente o pedido atual em memória e persiste lotes no MongoDB.

Assim, a memória é limitada aproximadamente ao maior pedido mais o lote configurado, e não ao tamanho do arquivo. O staging usa disco temporário proporcional ao arquivo recebido.

Configurações relevantes:

```env
LEGACY_IMPORT_BATCH_SIZE=1000
LEGACY_IMPORT_STAGING_COMMIT_SIZE=1000
LEGACY_IMPORT_INVALID_DETAIL_LIMIT=100
LEGACY_IMPORT_MAX_UPLOAD_KB=2097152
```

O limite padrão de upload é 2 GiB. O PHP usa `memory_limit=256M`; o processamento não depende de memória ilimitada.

## Validação e resiliência

Cada linha não vazia deve possuir exatamente 95 bytes:

| Campo | Offset | Tamanho |
|---|---:|---:|
| ID do usuário | 0 | 10 |
| Nome | 10 | 45 |
| ID do pedido | 55 | 10 |
| ID do produto | 65 | 10 |
| Valor | 75 | 12 |
| Data (`Ymd`) | 87 | 8 |

IDs, moeda e data são validados estritamente. Valores monetários são representados em centavos e formatados com duas casas. Linhas inválidas não interrompem o arquivo; todas são contabilizadas, mas apenas uma quantidade configurável de exemplos permanece na resposta do processamento e os eventos são registrados em log.

## Persistência, idempotência e índices

Cada documento MongoDB representa um pedido e contém seus produtos. Após agrupar o arquivo completo no staging, a persistência usa `upsert` por `order_id` e substitui deterministicamente o documento inteiro. Reimportar o mesmo arquivo não duplica produtos nem altera totais.

Trade-off: importar posteriormente um arquivo parcial contendo um pedido já existente substitui aquele pedido pela versão presente no novo arquivo. Essa escolha mantém simplicidade e idempotência sem assumir que `product_id` seja único — os arquivos oficiais demonstram que ele pode se repetir dentro do pedido.

Os índices são criados automaticamente ao iniciar a aplicação:

- `order_id` único;
- `date`;
- `user_id`.

## Filas e falhas

O worker possui cinco tentativas, timeout de 20 minutos e backoff progressivo. Se MongoDB estiver temporariamente indisponível, o job falha e é repetido; o arquivo permanece no volume. Se Redis estiver indisponível no recebimento, a API retorna uma mensagem pública genérica com `trace_id`, enquanto os detalhes ficam apenas nos logs.

Não foi criado endpoint de status de importação: ele não faz parte do contrato obrigatório do desafio. Em uma evolução, o UUID da importação poderia ser persistido com estados `pending`, `processing`, `completed` e `failed`.

## Testes e operação

```bash
docker compose exec app php artisan test
docker compose exec app php artisan route:list
docker compose ps
docker compose logs app
docker compose logs worker
docker compose logs mongo
docker compose logs redis
```

Os testes cobrem layout, valores monetários, datas impossíveis, linhas corrompidas, agregação, produtos repetidos, lotes, upload assíncrono, validação de arquivos, filtros, contrato JSON e ciclo de vida do arquivo temporário.

## Uso de inteligência artificial

GitHub Copilot e OpenAI Codex foram utilizados como apoio na revisão arquitetural, identificação de casos de borda, criação de testes e documentação. As sugestões foram confrontadas com o PDF e os arquivos oficiais; as decisões foram revisadas, o código foi validado e os testes foram executados pelo autor. Nenhuma saída de IA deve ser considerada substituta da compreensão do código entregue.
