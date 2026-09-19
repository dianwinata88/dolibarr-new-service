# Conventions

Conventions for the Dolibarr CRM extraction service. This is an API-only
microservice carved out of the Dolibarr monolith (strangler-fig). It must
replicate upstream CRM behavior exactly — do not "improve" or redesign
business logic.

## Stack

PHP 8.3 · Symfony 7.4 · API Platform 4 · Doctrine ORM · FrankenPHP · MariaDB 11.4

## Source layout — `src/<Domain>/`

Code is organized by **domain**, not by technical type:

```
src/
  <Domain>/                # e.g. src/Crm/
    Entity/                # Doctrine entities (llx_* tables)
    Repository/            # Doctrine repositories
    ApiResource/           # API Platform resources / DTOs / processors / providers
    <anything domain-specific>
  Controller/              # non-domain infrastructure endpoints (healthz, ...)
  Security/                # authentication (API key today, OIDC later)
```

- New classes under `src/` need **no service registration** — autowiring +
  autodiscovery cover controllers, processors, providers, and authenticators.
- Doctrine's attribute mapping scans all of `src/` (`prefix: App`), so entities
  may live in `src/<Domain>/Entity/` or anywhere else that fits the domain.
- API Platform resources are discovered via `#[ApiResource]` attributes.

## Database — `llx_*` naming and entity scoping

- Keep upstream table names verbatim: `llx_societe`, `llx_socpeople`, etc.
  (the `llx_` prefix is part of the name — no prefix stripping).
- Keep upstream column names verbatim (`nom`, `fk_soc`, `datec`, ...).
- **`entity` column semantics**: every multi-entity-scoped table has an
  `entity` column; Dolibarr defaults it to `1` for the master entity and
  filters on `entity IN (<shared ids>)`. Replicate this filtering in every
  repository/provider — never return rows across entities.
- **`fk_*` columns stay plain integers** — upstream does not enforce foreign
  keys, and neither do we. Do not create Doctrine associations/foreign keys;
  map `fk_*` as `integer` columns.
- Soft deletes: upstream uses `fk_statut`/`status` flags and `tms` timestamps;
  replicate as-is.

## Composer — do NOT modify `composer.json` / `composer.lock`

Dependencies are pinned and installed in this skeleton. **Do not add, remove,
or upgrade packages.** If you believe a package is missing, implement with
what exists and note the missing dependency in your structured output.

## Configuration

- Do not edit shared config outside your assigned files.
- Secrets live in env vars only: `.env` holds non-secret dev defaults,
  `.env.example` holds placeholders. Never commit real keys.
- Auth: requests authenticate with the `DOLAPIKEY` header or `api_key`
  query parameter, checked against the comma-separated `DOLIBARR_API_KEYS`
  env var (`App\Security\ApiKeyAuthenticator`). Public paths: `/healthz`,
  `/api/docs*`. Everything else requires `ROLE_API`.

## Tests

```
tests/
  <Domain>/        # PHPUnit, mirrors src/<Domain>/
  hurl/            # HTTP-level smoke tests (run against the running service)
```

- PHPUnit: `docker compose exec php bin/phpunit` (or `php bin/phpunit` locally).
- Hurl: `docker compose --profile test run --rm hurl`.
- Static analysis: `vendor/bin/phpstan analyse` (level 5, `src/`).
- Code style: `vendor/bin/phpcs` / `vendor/bin/phpcbf` (PSR-12).

## Running

```
cp .env.example .env.local   # or rely on committed .env dev defaults
docker compose up --build    # boots php (FrankenPHP) + mariadb
curl http://localhost/healthz
curl -H 'DOLAPIKEY: dolibarr-dev-key' http://localhost/api
```
