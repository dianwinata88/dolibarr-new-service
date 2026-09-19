-- Dev seed data for the Dolibarr CRM service.
--
-- Load into the dev database:
--   docker compose exec -T database mariadb -u root -proot dolibarr_crm < fixtures/seed.sql
--
-- Idempotent: every row is keyed by a fixed rowid and skipped if present.
-- Entity 1 only — the dev API key (dolibarr-dev-key) maps to login "admin", entity 1.

-- API user referenced by DOLIBARR_API_KEYS / sales-representative endpoints
INSERT INTO llx_user (rowid, login, entity, statut)
SELECT 1, 'admin', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM llx_user WHERE rowid = 1);

-- Demo thirdparty
INSERT INTO llx_societe (rowid, nom, name_alias, entity, client, fournisseur, status, code_client, datec)
SELECT 10, 'Acme Corp', '', 1, 1, 0, 1, 'CU2401-00001', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_societe WHERE rowid = 10);

-- Contact attached to it
INSERT INTO llx_socpeople (rowid, lastname, firstname, entity, fk_soc, statut, datec)
SELECT 10, 'Doe', 'Jane', 1, 10, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_socpeople WHERE rowid = 10);

-- Category (type 2 = customer) + link
INSERT INTO llx_categorie (rowid, fk_parent, label, type, entity, visible, date_creation, fk_user_creat, fk_user_modif)
SELECT 10, 0, 'VIP', 2, 1, 1, NOW(), 1, NULL
WHERE NOT EXISTS (SELECT 1 FROM llx_categorie WHERE rowid = 10);

INSERT INTO llx_categorie_societe (fk_categorie, fk_soc)
SELECT 10, 10
WHERE NOT EXISTS (SELECT 1 FROM llx_categorie_societe WHERE fk_categorie = 10 AND fk_soc = 10);

-- Bank account (RIB); upstream stores the IBAN in `iban_prefix`
INSERT INTO llx_societe_rib (rowid, fk_soc, label, bank, bic, iban_prefix, default_rib, entity, datec)
SELECT 10, 10, 'Main account', 'BNP Paribas', 'BNPAFRPP', 'FR7630006000011234567890189', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_societe_rib WHERE rowid = 10);

-- External-site account (e.g. Stripe customer id) for GET /accounts/{site}/{key}
INSERT INTO llx_societe_account (rowid, entity, login, site, key_account, fk_soc, date_creation, fk_user_creat)
SELECT 10, 1, 'seed', 'stripe', 'cus_DEMO001', 10, NOW(), 1
WHERE NOT EXISTS (SELECT 1 FROM llx_societe_account WHERE rowid = 10);
