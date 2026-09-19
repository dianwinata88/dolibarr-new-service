# Endpoint parity — Dolibarr CRM REST API

Reference: `Dolibarr/dolibarr` @ `a30c3c7` (develop, v25.0.0-alpha).
Upstream classes: `api_thirdparties.class.php`, `api_contacts.class.php`,
`api_categories.class.php` under `htdocs/societe/class/` /
`htdocs/categories/class/`.

Auth: `DOLAPIKEY` header, `api_key`/`DOLAPIKEY` query params, and
`Authorization: Bearer` are all accepted, like upstream Restler.
`DolApiEntity` selects the entity. Error bodies use the upstream shape
`{"error":{"code":<int>,"message":"..."}}`; API-Platform-managed bank-account
endpoints additionally carry RFC7807 fields (`detail`, `type`).

## Thirdparties — `/api/thirdparties` (upstream: `api/index.php/thirdparties`)

| Upstream `@url` | Route | Status | Notes |
|---|---|---|---|
| `GET /` | `GET /api/thirdparties` | ported | `sortfield/sortorder/limit/page/properties/sqlfilters` supported |
| `GET {id}` | `GET /api/thirdparties/{id}` | ported | `_cleanObjectDatas` field pruning replicated (`nom` unset, `array_options`, etc.) |
| `GET email/{email}` | `GET /api/thirdparties/email/{email}` | ported | |
| `GET barcode/{barcode}` | `GET /api/thirdparties/barcode/{barcode}` | ported | |
| `POST /` | `POST /api/thirdparties` | ported | returns raw int id |
| `PUT {id}` | `PUT /api/thirdparties/{id}` | ported | returns cleaned object |
| `PUT {id}/merge/{idtodelete}` | same | ported | |
| `DELETE {id}` | same | ported | returns `{"success":{"code":200,...}}` |
| `PUT {id}/setpricelevel/{priceLevel}` | same | ported | gates preserved: 501 without `societe`/`product` module, 501 when `PRODUIT_MULTIPRICES` off, 400 bounds; stale in-memory `price_level` in response preserved |
| `GET {id}/representative` | same | ported | |
| `POST {id}/representative/{representative_id}` | same | ported | returns int |
| `DELETE {id}/representative/{representative_id}` | same | ported | |
| `GET {id}/categories` / `PUT / DELETE {id}/categories/{category_id}` | same | ported | |
| `GET/PUT/DELETE {id}/supplier_categories[/{category_id}]` | same | ported | |
| `GET {id}/outstandingproposals` / `outstandingorders` / `outstandinginvoices` | same | ported | refs keyed by object id; empty → `[]` (PHP array serialization) |
| `GET {id}/getinvoicesqualifiedforreplacement` / `getinvoicesqualifiedforcreditnote` | same | ported | invoice tables out of scope → empty result sets |
| `GET/POST {id}/notifications`, `POST {id}/notificationsbycode/{code}`, `PUT/DELETE {id}/notifications/{notification_id}` | same | ported | `llx_notify_def` |
| `GET {id}/bankaccounts` | same | ported | 404 `Account not found` on empty list |
| `POST {id}/bankaccounts` | same | ported | RUM auto-generated when absent |
| `PUT/DELETE {id}/bankaccounts/{bankaccount_id}` | same | ported | 403 `Not allowed due to bad consistency of input data` on socid mismatch |
| `GET {id}/generateBankAccountDocument/{companybankid}/{model}` | same | partial | persists `model_pdf` like `setDocModel`; PDF/ODT generation out of scope (UI domain), returns `{"success":0}` |
| `GET {id}/accounts/` | `GET /api/thirdparties/{id}/accounts` | ported | `llx_societe_account` |
| `GET /accounts/{site}/{key_account}` | same | ported | 404 `This account have many thirdparties attached or does not exist.` unless exactly 1 row |
| `POST {id}/accounts` and `POST {id}/accounts/{site}` | same | ported | |
| `PUT {id}/accounts/{site}` / `DELETE {id}/accounts/{site}` / `DELETE {id}/accounts` | same | ported | |
| `GET {id}/fixedamountdiscounts` | same | ported | `llx_societe_remise_except` |
| `POST {id}/fixedamountdiscounts` | same | ported | VAT applied only when `vat_src_code` given (upstream quirk preserved) → 201 |
| `POST {id}/splitdiscount/{discountid}` | same | ported | 405 when amounts don't sum to TTC |
| `DELETE {id}/fixedamountdiscounts/{discountid}` | same | ported | |
| `GET {id}/availablediscounts` | same | ported | |
| — | `GET /api/thirdparties/{id}/pricelevels` | addition | `llx_societe_prices` history (UI-only upstream, `societe/price.php`) |
| — | `GET/POST /api/thirdparties/{id}/relativediscounts` | addition | `llx_societe_remise` history + `set_remise_client` (UI-only upstream, `comm/remise.php`) |
| — | `GET /api/thirdparties/{id}/contacts` | addition | convenience filter; upstream lists contacts via `GET /api/contacts?socid=` |

## Contacts — `/api/contacts` (upstream: `api/index.php/contacts`)

| Upstream `@url` | Route | Status | Notes |
|---|---|---|---|
| `GET /` (index, `?socid=`) | `GET /api/contacts` | ported | `includecount/includeroles`, sort/page/sqlfilters |
| `GET {id}` | `GET /api/contacts/{id}` | ported | `includecount`, `includeroles` |
| `GET email/{email}` | `GET /api/contacts/email/{email}` | ported | duplicate-email quirk preserved (unpopulated 200) |
| `POST /` | `POST /api/contacts` | ported | returns int |
| `PUT {id}` / `DELETE {id}` | same | ported | |
| `GET {id}/categories`, `PUT/DELETE {id}/categories/{category_id}` | same | ported | |
| `POST {id}/createUser` | — | not ported | creates `llx_user`; user management out of CRM scope |

## Categories — `/api/categories` (upstream: `api/index.php/categories`)

| Upstream `@url` | Route | Status | Notes |
|---|---|---|---|
| `GET /` | `GET /api/categories` | ported | `type` filter accepts id or MAP_ID string |
| `GET /types` | `GET /api/categories/types` | ported | MAP_ID string→label map |
| `GET {id}` | `GET /api/categories/{id}` | ported | |
| `POST /` | `POST /api/categories` | ported | `type` int-cast like upstream (`"customer"` → `0` product, verbatim `_checkValForAPI`) |
| `PUT {id}` / `DELETE {id}` | same | ported | |
| `GET {id}/objects` | same | ported | `type` param required |
| `GET /object/{type}/{id}` | `GET /api/categories/object/{type}/{id}` | ported | |
| `POST {id}/objects/{type}/{object_id}` and `…/ref/{object_ref}` | same | ported | link |
| `DELETE {id}/objects/{type}/{object_id}` and `…/ref/{object_ref}` | same | ported | unlink |

## Dictionaries — `/api/setup/dictionary`

`GET /api/setup/dictionary/contact_types` and `GET /api/setup/dictionary/civilities`
ported (read-only `llx_c_*` dictionaries used by the CRM endpoints).

## Cross-cutting parity notes

- `DolApiEntity` header → `entity` scoping (`IN (entity,…)` on every query).
- `_checkAccessToResource` emulated via `DOLIBARR_API_SOCID` (internal user = all).
- `_cleanObjectDatas` dynamic-property pruning: `absolute_discount`,
  `absolute_creditnote` emitted; `nom` alias unset; `datec` not emitted.
- `fetch` return-code semantics preserved: `0` → 404, `<0`/`>1` leak past the
  `if (!$result)` check exactly like upstream.
- Not ported (non-CRM / out of scope): document generation bodies (PDF/ODT),
  `POST contacts/{id}/createUser`, trigger/event bus, mailings.
