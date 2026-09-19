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
Optional DB UI (Adminer): `docker compose --profile tools up` → http://localhost:8080

## Auth

Dolibarr-compatible API keys. Accepted locations, in order: `api_key` /
`DOLAPIKEY` query params, the `DOLAPIKEY` header (recommended), or
`Authorization: Bearer <key>` as fallback.

Keys come from the `DOLIBARR_API_KEYS` env var, a JSON map of
`key → {"login": "...", "entity": <int>}`. The key's `entity` scopes every
request (upstream `$conf->entity`); a `DOLAPIENTITY` header may assert it
and is rejected with 401 on mismatch. A bare comma-separated key list is
also accepted and maps to entity `1`. Dev default key: `dolibarr-dev-key`.

Errors follow the upstream shape `{"error":{"code":NNN,"message":"..."}}`.
Downstream code reads the scope via `App\Security\EntityContext`.

See docs/RUNNING.md for the full operating guide.

## Tests & QA

```bash
docker compose exec php bin/phpunit                 # PHPUnit
docker compose --profile test run --rm hurl         # HTTP smoke tests
docker compose exec php vendor/bin/phpstan analyse  # static analysis
docker compose exec php vendor/bin/phpcs            # PSR-12 style check
```

See `docs/CONVENTIONS.md` for layout and contribution rules — including the
**no composer.json/composer.lock edits** rule for domain agents.
