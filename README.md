<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Desafio Luiza Labs — Vertical Logística

Este repositório entrega uma API Laravel 12 para processar arquivos legados de pedidos em formato de texto fixo, normalizar o payload em JSON e expor os dados via REST.

### Tecnologias usadas
- Laravel 12
- PHP 8.4 via Docker
- MongoDB como persistência primária
- Enfileiramento assíncrono via Redis quando MongoDB estiver indisponível
- Logs de monitoramento em pt_BR via `Log::info` / `Log::warning` / `Log::error`
- Processamento em stream de arquivo para reduzir uso de memória

### O que está implementado
- `POST /api/orders/import`: recebe arquivo multipart e processa linha a linha
- `GET /api/orders`: lista pedidos com filtros `order_id`, `date_start`, `date_end`
- `GET /api/orders/{orderId}`: recupera pedido pelo ID
- Resiliência para linhas corrompidas: falhas são registradas e a leitura continua
- Armazenamento primário em MongoDB e fallback em arquivo JSON

### Como rodar com Docker
1. Copie `.env.example` para `.env`
2. Ajuste `.env` se desejar
3. Execute:

```bash
docker compose up --build
```

4. A API estará disponível em `http://localhost:8000`

### Endpoints
- `POST /api/orders/import` com `multipart/form-data` e campo `file`
- `GET /api/orders`
- `GET /api/orders/{orderId}`

### Observações importantes
- A solução exige PHP 8.2 para Laravel 12; se o host usa PHP 8.1, use o Docker para desenvolvimento.
- MongoDB está configurado em `docker-compose.yml` como serviço `mongo` e pode ser substituído pela variável de ambiente `MONGO_URI`.
- Quando o MongoDB estiver indisponível, uploads de importação são enfileirados no Redis e processados por um worker assim que o banco voltar.
- Os arquivos enfileirados são armazenados em `storage/app/queue_uploads` e não devem ser versionados.

### Uso de filas Redis
Para processar importações assíncronas quando o Mongo estiver offline:

1. Ajuste `.env`:
```
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```
2. Rode o Redis localmente ou via Docker:
```bash
docker run -d --name redis -p 6379:6379 redis:7
```
3. Inicie o worker de fila:
```bash
php artisan queue:work --tries=3
```
4. Faça o upload normalmente; se o Mongo estiver indisponível o arquivo será enfileirado e o usuário receberá confirmação de processamento assíncrono.

### Uso de IA
- Foi utilizado GitHub Copilot gerar a base de testes, definir o parser de linhas fixas e escrever o README.

### Licença
MIT
