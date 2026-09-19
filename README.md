# dolibarr-new-service

Standalone CRM microservice extracted from the Dolibarr ERP monolith
(strangler-fig migration). API-only — no UI.

Stack: PHP 8.3, Symfony 7.4, API Platform 4, Doctrine ORM, FrankenPHP, MariaDB.

## Quick start

```bash
docker compose up --build
curl http://localhost/healthz                       # {"status":"ok","service":"dolibarr-crm"}
curl -H 'DOLAPIKEY: dolibarr-dev-key' http://localhost/api
```

API docs (Swagger UI): http://localhost/api/docs

## Auth

`DOLAPIKEY` header or `api_key` query param, checked against the
comma-separated `DOLIBARR_API_KEYS` env var. Dev default: `dolibarr-dev-key`.

## Tests & QA

```bash
docker compose exec php bin/phpunit                 # PHPUnit
docker compose --profile test run --rm hurl         # HTTP smoke tests
docker compose exec php vendor/bin/phpstan analyse  # static analysis
docker compose exec php vendor/bin/phpcs            # PSR-12 style check
```

See `docs/CONVENTIONS.md` for layout and contribution rules — including the
**no composer.json/composer.lock edits** rule for domain agents.
