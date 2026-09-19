# CRM Schema — Dolibarr → Doctrine port

Port of the Dolibarr CRM table set (from `htdocs/install/mysql/tables/` in
`Dolibarr/dolibarr`, dev branch) into plain Doctrine entities. The migration
`migrations/Version20260919155802.php` executes the upstream `CREATE TABLE` /
`ADD INDEX` statements **verbatim**, so the physical schema — column types,
nullability, defaults, index names — matches the monolith exactly.

Conventions applied (see `docs/CONVENTIONS.md`):

- Upstream table and column names kept verbatim (`llx_*`, snake_case).
- `fk_*` columns are plain integers — **no Doctrine associations, no FK
  constraints** (upstream mostly doesn't enforce them either).
- `entity` columns kept (`DEFAULT 1 NOT NULL`) — repositories/providers must
  filter on them.
- Tables are created `ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
  (upstream relies on the server default; made explicit).
- Entities live in `src/Entity/` (`App\Entity`); dictionary tables in
  `src/Entity/Dictionary/` (`App\Entity\Dictionary`).

## Table → entity map

### Third parties (`llx_societe` group)

| Upstream table | Entity | Notes |
|---|---|---|
| `llx_societe` | `App\Entity\Societe` | Extrafields-aware. Unique keys on `code_client`/`code_fournisseur`/`barcode`/`prefix_comm` + `entity`. |
| `llx_societe_extrafields` | `App\Entity\SocieteExtrafields` | Extra field *values* for `llx_societe` (joined on `fk_object`); unique `uk_societe_extrafields(fk_object)`. Dynamic extra columns are not entity fields — see Extrafields below. |
| `llx_societe_commerciaux` | `App\Entity\SocieteCommerciaux` | Sales-rep assignment; composite unique `(fk_soc, fk_user, fk_c_type_contact_code)`. |
| `llx_societe_prices` | `App\Entity\SocietePrices` | No extra keys upstream. |
| `llx_societe_remise` | `App\Entity\SocieteRemise` | Customer fixed discounts. |
| `llx_societe_remise_except` | `App\Entity\SocieteRemiseExcept` | One-off discounts (credit notes / deposits). Upstream FKs to `llx_facture*`, `llx_user` intentionally dropped. |
| `llx_societe_rib` | `App\Entity\SocieteRib` | Bank/PayPal/card/Stripe account details. |
| `llx_societe_account` | `App\Entity\SocieteAccount` | External-site accounts. |
| `llx_societe_perentity` | `App\Entity\SocietePerEntity` | Source file `llx_societe_perentity-multicompany.sql`; unique `(fk_soc, entity)`. |
| `llx_societe_log` | `App\Entity\SocieteLog` | Legacy table — **not** in `install/mysql/tables` on current upstream; ported from `dev/initdemo/mysqldump_dolibarr_24.0.0.sql` where it still exists. |

### Contacts (`llx_socpeople` group)

| Upstream table | Entity | Notes |
|---|---|---|
| `llx_socpeople` | `App\Entity\Socpeople` | Extrafields-aware. |
| `llx_socpeople_extrafields` | `App\Entity\SocpeopleExtrafields` | Unique `uk_socpeople_extrafields(fk_object)`. |
| `llx_societe_contacts` | `App\Entity\SocieteContact` | soc ↔ contact link with role; unique `(entity, fk_soc, fk_c_type_contact, fk_socpeople)`. Note: upstream `tms TIMESTAMP` (bare) — nullable plain timestamp, no auto-update. |

### Categories (`llx_categorie` group)

| Upstream table | Entity | Notes |
|---|---|---|
| `llx_categorie` | `App\Entity\Categorie` | Extrafields-aware. Unique `uk_categorie_ref(entity, fk_parent, label, type)`. |
| `llx_categories_extrafields` | `App\Entity\CategoriesExtrafields` | Upstream name is `llx_categories_extrafields` (plural "categories") — task text said `llx_categorie_extrafields`; upstream spelling kept. |
| `llx_categorie_societe` | `App\Entity\CategorieSociete` | Composite PK `(fk_categorie, fk_soc)` — no `rowid`. |
| `llx_categorie_fournisseur` | `App\Entity\CategorieFournisseur` | Composite PK `(fk_categorie, fk_soc)`. |
| `llx_categorie_contact` | `App\Entity\CategorieContact` | Composite PK `(fk_categorie, fk_socpeople)`. |

### Reference dictionaries (`llx_c_*`)

All in `App\Entity\Dictionary`. These are read-mostly lookup tables; several
use non-autoincrement integer PKs (`id`) or a varchar PK (`code`), exactly as
upstream.

| Upstream table | Entity | PK |
|---|---|---|
| `llx_c_typent` | `Typent` | `id` (manual) |
| `llx_c_country` | `Country` | `rowid` (manual) |
| `llx_c_departements` | `Departement` | `rowid` auto |
| `llx_c_paiement` | `Paiement` | `id` auto |
| `llx_c_payment_term` | `PaymentTerm` | `rowid` auto |
| `llx_c_incoterms` | `Incoterms` | `rowid` auto |
| `llx_c_stcomm` | `Stcomm` | `id` (manual) |
| `llx_c_stcommcontact` | `Stcommcontact` | `id` (manual) |
| `llx_c_effectif` | `Effectif` | `id` (manual) |
| `llx_c_forme_juridique` | `FormeJuridique` | `rowid` auto |
| `llx_c_civility` | `Civility` | `rowid` auto |
| `llx_c_type_contact` | `TypeContact` | `rowid` auto; unique `(element, source, code)` |
| `llx_c_input_method` | `InputMethod` | `rowid` auto |
| `llx_c_prospectlevel` | `Prospectlevel` | `code` varchar(12) |

`llx_c_departements.fk_region` references `llx_c_regions` upstream; that table
is outside this slice and was **not** ported (the unique key and index on
`fk_region` are kept).

### User stub

| Upstream table | Entity | Notes |
|---|---|---|
| `llx_user` | `App\Entity\User` | Minimal stub: `rowid` (PK, auto), `login`, `firstname`, `lastname`, `entity`, plus upstream `uk_user_login(login, entity)`. Real users are owned by the identity service; `fk_user_*` columns elsewhere are soft int references to `llx_user.rowid` (upstream PK name `rowid` kept for join compatibility). |

## Custom DBAL types

`config/packages/doctrine.yaml` registers two platform types so the physical
schema stays identical to upstream while introspection/validation works:

- `tinyint` → `App\DBAL\TinyIntType` — upstream `tinyint` columns are *not*
  booleans (e.g. `llx_societe.client` is 0/1/2); DBAL would otherwise map
  them to `boolean`. Declared as `type: 'tinyint'` on entity fields.
- `point` → `App\DBAL\PointType` — MariaDB spatial column for
  `llx_societe.geopoint` / `llx_socpeople.geopoint`; values hydrate as the
  driver's raw (binary WKB) string.

Other mapping notes:

- `tms` columns (`timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE
  CURRENT_TIMESTAMP`) map to `datetime` + `default: CURRENT_TIMESTAMP` in
  metadata — the migration creates them as real `timestamp ... ON UPDATE`
  columns. If you add a new `tms`-style column, keep `datetime` type and rely
  on hand-written DDL for the `ON UPDATE` clause (it cannot be expressed in
  Doctrine column options).
- `text` columns are declared with `length: 65535` so Doctrine emits `TEXT`
  (not `LONGTEXT`).
- `double(p,s)` columns map to `float` with matching `precision`/`scale`;
  defaults are declared as the MariaDB-normalized string form
  (`'0.00000000'`) because the schema comparator compares rendered DDL.

## Extrafields mechanism

Upstream stores extra field *definitions* in `llx_extrafields` (not in this
slice's scope) and *values* in `<object>_extrafields` tables with one column
per field, joined on `fk_object = <object>.rowid`. Since the column set is
dynamic, values are exposed as a map on the entity — matching upstream's
`$object->array_options` (extra fields appear inline on the object payload).

Usage for entities marked `#[Extrafields(table: 'llx_..._extrafields')]` and
implementing `ExtrafieldsAwareInterface` (via `ExtrafieldsAwareTrait`):

- **Read** — automatic: `ExtrafieldsListener` (Doctrine `postLoad`) loads the
  extrafields row into `$entity->getExtrafields()` as
  `['<extra_col>' => <value>, ...]` (internal columns `rowid`, `tms`,
  `fk_object`, `import_key` stripped).
- **Write** — call `ExtrafieldsWriter::save($entity)` after flushing the
  entity (needs `rowid`). Mirrors upstream `insertExtraFields()`: deletes the
  existing row and re-inserts the current values in one transaction; keys not
  matching real columns are ignored.
- API Platform resources added later can expose `extrafields` as a plain
  array field on the payload and call the writer from their processor.

Intentionally not ported: `llx_extrafields` (field definitions — admin/setup
scope; the writer discovers value columns via `information_schema` instead).
