#!/usr/bin/env bash
# Compare the service-owned CRM schema against upstream Dolibarr table
# definitions.
#
# Usage:
#   scripts/schema_diff.sh [--upstream /path/to/dolibarr] [--dsn 'mysql://user:pass@host/db']
#
# Defaults:
#   --upstream  $DOLIBARR_DIR (a checkout of Dolibarr/dolibarr)
#   --dsn       DATABASE_URL env or the docker-compose dev database
#
# For every table that exists in the live schema AND has an upstream
# htdocs/install/mysql/tables/llx_<name>.sql file, the script diffs the
# column-name sets. Tables without an upstream counterpart (service-local
# like doctrine_migration_versions) and upstream files not ported are listed
# for information only.

set -euo pipefail

UPSTREAM="${DOLIBARR_DIR:-}"
DSN="${DATABASE_URL:-mysql://dolibarr:dolibarr@localhost:3306/dolibarr_crm}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --upstream) UPSTREAM="$2"; shift 2 ;;
    --dsn)      DSN="$2"; shift 2 ;;
    -h|--help)  grep '^#' "$0" | head -20; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

if [[ -z "$UPSTREAM" || ! -d "$UPSTREAM/htdocs/install/mysql/tables" ]]; then
  echo "error: pass a Dolibarr checkout via --upstream or DOLIBARR_DIR" >&2
  exit 2
fi

# Parse mysql://user:pass@host:port/db
url="${DSN#mysql://}"; url="${url#mariadb://}"; url="${url%%\?*}"
creds="${url%%@*}"; hostdb="${url#*@}"
user="${creds%%:*}"; pass="${creds#*:}"; [[ "$pass" == "$creds" ]] && pass=""
hostport="${hostdb%%/*}"; db="${hostdb#*/}"
host="${hostport%%:*}"; port="${hostport#*:}"; [[ "$port" == "$hostport" ]] && port=3306

args=(-h "$host" -P "$port" -u "$user")
[[ -n "$pass" ]] && args+=(-p"$pass")
args+=("$db")

# DB client: local mariadb, else the compose `database` service
if command -v mariadb >/dev/null 2>&1; then
  MARIADB=(mariadb --batch --skip-column-names "${args[@]}")
elif command -v docker >/dev/null 2>&1 && docker compose ps database >/dev/null 2>&1; then
  MARIADB=(docker compose exec -T database mariadb --batch --skip-column-names -u root -proot "$db")
else
  echo "error: no mariadb client and no docker compose 'database' service" >&2
  exit 2
fi

mapfile -t db_tables < <("${MARIADB[@]}" -e "SHOW TABLES;")

fail=0
echo "== column diff (tables present in both) =="
for t in "${db_tables[@]}"; do
  [[ "$t" == llx_* ]] || { echo "  [local-only] $t"; continue; }
  sql="$UPSTREAM/htdocs/install/mysql/tables/$t.sql"
  if [[ ! -f "$sql" ]]; then
    # upstream multi-table files like llx_societe_perentity-multicompany.sql
    sql=$(compgen -G "$UPSTREAM/htdocs/install/mysql/tables/${t}-*.sql" | head -1 || true)
  fi
  if [[ -z "$sql" || ! -f "$sql" ]]; then
    echo "  [no-upstream-file] $t"
    continue
  fi
  mapfile -t db_cols < <("${MARIADB[@]}" -e \
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$t' ORDER BY ORDINAL_POSITION;")
  # crude column extraction: identifier at start of a line inside CREATE TABLE
  mapfile -t up_cols < <(awk '
      match(tolower($0), /^[[:space:]]*([a-z_][a-z0-9_]*|`[^`]+`)[[:space:]]+(tinyint|smallint|mediumint|int|integer|bigint|varchar|char|text|mediumtext|longtext|datetime|timestamp|date|time|float|double|decimal|numeric|real|boolean|blob|year|enum|set|point|geometry)/, m) {
        c = m[1]; gsub(/`/, "", c); print c
      }' "$sql" | sort -u)
  missing=$(comm -23 <(printf '%s\n' "${up_cols[@]}") <(printf '%s\n' "${db_cols[@]}" | sort -u) | tr '\n' ' ')
  extra=$(comm -13 <(printf '%s\n' "${up_cols[@]}") <(printf '%s\n' "${db_cols[@]}" | sort -u) | tr '\n' ' ')
  if [[ -n "$missing" || -n "$extra" ]]; then
    fail=1
    echo "  $t"
    [[ -n "$missing" ]] && echo "    upstream-only: $missing"
    [[ -n "$extra" ]]    && echo "    service-only:  $extra"
  fi
done

echo "== upstream CRM files not yet ported =="
for f in "$UPSTREAM"/htdocs/install/mysql/tables/llx_*.sql; do
  base="$(basename "$f" .sql)"; base="${base%%.key}"
  found=0
  for t in "${db_tables[@]}"; do [[ "$t" == "$base" || "$t" == "${base%%-*}" ]] && { found=1; break; }; done
  [[ $found -eq 0 ]] && echo "$base"
done | sort -u | sed 's/^/  /'

exit $fail
