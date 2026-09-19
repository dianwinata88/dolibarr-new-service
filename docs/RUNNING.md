# Running the Dolibarr CRM service

Standalone CRM microservice extracted from the Dolibarr ERP monolith
(strangler-fig migration). API-only — no UI.

Stack: PHP 8.3 · Symfony 7.4 · API Platform 4 · Doctrine ORM · FrankenPHP · MariaDB 11.4

## Local development (Docker Compose)

```bash
cp .env.example .env.local    # optional: .env already ships safe dev defaults
docker compose up --build
```

Services:

| Service   | Port | Notes                                        |
| --------- | ---- | -------------------------------------------- |
| `php`     | 80   | FrankenPHP serving the Symfony app (dev, hot-reload via `--watch`) |
| `database`| 3306 | MariaDB 11.4 (`dolibarr`/`dolibarr`)         |
| `adminer` | 8080 | optional DB UI — `docker compose --profile tools up` |

Smoke checks:

```bash
curl http://localhost/healthz                                  # {"status":"ok","service":"dolibarr-crm"}
curl -H 'DOLAPIKEY: dolibarr-dev-key' http://localhost/api     # Hydra entrypoint
```

API documentation (Swagger UI / OpenAPI): http://localhost/api/docs

### Seed data

```bash
docker compose exec -T database mariadb -u root -proot dolibarr_crm < fixtures/seed.sql
```

Creates an `admin` `llx_user` row plus a demo thirdparty / contact / category /
bank account / external-site account (idempotent, entity 1).

### Endpoint & schema parity

- `docs/PARITY.md` — endpoint-by-endpoint matrix vs upstream `api_*.class.php`.
- `scripts/schema_diff.sh --upstream /path/to/dolibarr` — diffs the live
  schema's columns against upstream `htdocs/install/mysql/tables/llx_*.sql`
  (requires a Dolibarr checkout; uses the compose `database` service when no
  local `mariadb` client exists).

## Authentication

Dolibarr-compatible static API keys (`DolibarrApiAccess` semantics). The key
is accepted in any of these locations, in order of precedence:

1. `api_key` query parameter (deprecated upstream, kept for compatibility)
2. `DOLAPIKEY` query parameter
3. `DOLAPIKEY` HTTP header — recommended
4. `Authorization: Bearer <key>` header — fallback only

Keys are configured with the `DOLIBARR_API_KEYS` env var, a JSON map:

```
DOLIBARR_API_KEYS='{"<key>":{"login":"<user>","entity":<int>}}'
```

- `login` — the Dolibarr login the key impersonates (upstream `llx_user.login`).
- `entity` — the multicompany entity the request is scoped to
  (upstream `llx_user.entity` / `$conf->entity`). `1` is the master entity.

A bare comma-separated list (`DOLIBARR_API_KEYS="key1,key2"`) is also
accepted and maps every key to login `api-client`, entity `1`.

Requests may send a `DOLAPIENTITY` header to assert the entity; as upstream,
it must match the key's entity or the request is rejected with 401.

Downstream code resolves the scope via `App\Security\EntityContext`
(`getEntity()`, `getLogin()`), `Security::getUser()` (an
`App\Security\ApiClientUser`), or the `_dolibarr_entity` /
`_dolibarr_login` request attributes.

### Error format

Auth failures use the upstream Dolibarr API JSON shape:

```json
{"error": {"code": 401, "message": "Failed to login to API. Neither parameter 'HTTP_DOLAPIKEY' nor 'Authentication: Bearer' found on HTTP header (and no parameter DOLAPIKEY in URL)."}}
```

- `401` — missing/invalid key, or `DOLAPIENTITY` mismatch
- `403` — authenticated but insufficient rights (`{"error":{"code":403,"message":"Forbidden"}}`)
- `503` — `dolcrypt:`-prefixed key (rejected like upstream)

### Migrating to OIDC/JWT

`App\Security\ApiKeyAuthenticator` is the only piece to replace: point the
`custom_authenticators` entry in `config/packages/security.yaml` at an
access-token handler returning an `ApiClientUser` (or implement
`UserInterface` with a `getEntity()` equivalent). Everything downstream
relies on `EntityContext`, not on the authenticator.

## Database

The service owns its own MariaDB schema (`dolibarr_crm` by default) and keeps
upstream `llx_*` table names, `entity` column semantics, and soft `fk_*`
references. Apply schema with Doctrine migrations:

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

`DATABASE_URL` overrides the default DSN, e.g.
`mysql://user:pass@host:3306/dbname?serverVersion=mariadb-11.4.0&charset=utf8mb4`.

## Tests & QA

```bash
docker compose exec php bin/phpunit                  # PHPUnit suite
docker compose --profile test run --rm hurl          # HTTP smoke tests (tests/hurl)
docker compose exec php vendor/bin/phpstan analyse   # static analysis (level 5)
docker compose exec php vendor/bin/phpcs             # PSR-12 code style
```

## Production image

The `Dockerfile` ships a multi-stage build; the `frankenphp_prod` target is
the production image:

```bash
docker build --target frankenphp_prod -t dolibarr-crm:prod .
docker run -p 8080:80 \
  -e DOLIBARR_API_KEYS='{"<key>":{"login":"<user>","entity":1}}' \
  -e DATABASE_URL='mysql://user:pass@db:3306/dolibarr_crm?serverVersion=mariadb-11.4.0&charset=utf8mb4' \
  -e APP_SECRET='<random>' \
  dolibarr-crm:prod
```

HTTPS is expected to be terminated by a reverse proxy in front of the service
(`auto_https off` in `frankenphp/Caddyfile`); set `SERVER_NAME` to the public
host. The image healthcheck hits `GET /healthz`.

### Environment variables

| Variable | Purpose | Default |
| -------- | ------- | ------- |
| `APP_ENV` / `APP_SECRET` | Symfony env / secret | `dev` / — |
| `DATABASE_URL` | Doctrine DBAL DSN | `mysql://dolibarr:dolibarr@database:3306/dolibarr_crm?serverVersion=mariadb-11.4.0&charset=utf8mb4` |
| `DOLIBARR_API_KEYS` | JSON map of API keys (see above) | dev key `dolibarr-dev-key` |
| `SERVER_NAME` | Caddy server name | `:80` (compose) |
| `HTTP_PORT` / `MARIADB_PORT` / `ADMINER_PORT` | published ports | `80` / `3306` / `8080` |
| `MARIADB_*` | database bootstrap credentials | `dolibarr` / `dolibarr` / `root` |
| `TRUSTED_PROXIES` / `TRUSTED_HOSTS` | proxy trust for the app | private ranges / `^localhost|php$` |
| `CORS_ALLOW_ORIGIN` | CORS origin regex | localhost only |
