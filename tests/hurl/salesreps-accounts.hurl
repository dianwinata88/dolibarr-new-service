# Golden path: sales representatives (llx_societe_commerciaux) and external
# site accounts (llx_societe_account).

POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"RepCorp","client":1}
HTTP 200
[Captures]
socid: jsonpath "$"

# no reps assigned yet -> empty list like upstream
GET {{host}}/api/thirdparties/{{socid}}/representative
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$" count == 0

# assigning a nonexistent user -> upstream 404
POST {{host}}/api/thirdparties/{{socid}}/representative/999999
DOLAPIKEY: {{api_key}}
HTTP 404

# representatives listing (same list endpoint)
GET {{host}}/api/thirdparties/{{socid}}/representatives
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$" count == 0

# ---- external accounts (llx_societe_account) ----

# no account yet -> 404 verbatim
GET {{host}}/api/thirdparties/{{socid}}/accounts
DOLAPIKEY: {{api_key}}
HTTP 404
[Asserts]
jsonpath "$.error.code" == 404

# create an account
POST {{host}}/api/thirdparties/{{socid}}/accounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"site":"stripe","key_account":"cus_{{newUuid}}"}
HTTP 200
[Captures]
acctkey: jsonpath "$.key_account"
[Asserts]
jsonpath "$.site" == "stripe"
jsonpath "$.key_account" == {{acctkey}}
jsonpath "$.fk_soc" == {{socid}}

# duplicate site -> 409 verbatim
POST {{host}}/api/thirdparties/{{socid}}/accounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"site":"stripe","key_account":"cus_OTHER"}
HTTP 409
[Asserts]
jsonpath "$.error.code" == 409

# reverse lookup: thirdparty by account
GET {{host}}/api/thirdparties/accounts/stripe/{{acctkey}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.id" == {{socid}}
jsonpath "$.name" == "RepCorp"

# unknown account -> 404 verbatim
GET {{host}}/api/thirdparties/accounts/stripe/cus_NOPE
DOLAPIKEY: {{api_key}}
HTTP 404
[Asserts]
jsonpath "$.error.message" == "This account have many thirdparties attached or does not exist."

# delete all accounts of the thirdparty
DELETE {{host}}/api/thirdparties/{{socid}}/accounts
DOLAPIKEY: {{api_key}}
HTTP 200

DELETE {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 200
