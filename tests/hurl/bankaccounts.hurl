# Smoke test: third-party bank accounts (llx_societe_rib) endpoints.
# Requires a llx_societe row with rowid=1 to exist; run after fixtures.
GET {{host}}/api/thirdparties/1/bankaccounts
DOLAPIKEY: {{api_key}}
Accept: application/json
HTTP *

# Unknown third party cannot receive a bank account
POST {{host}}/api/thirdparties/999999/bankaccounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
Accept: application/json
{"label":"x"}
HTTP 404
[Asserts]
jsonpath "$.detail" == "Error creating Company Bank account, Company doesn't exists"

# Missing key is rejected
GET {{host}}/api/thirdparties/1/bankaccounts
HTTP 401
