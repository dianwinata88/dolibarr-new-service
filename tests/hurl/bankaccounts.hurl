# Golden path: third-party bank accounts / RIB (llx_societe_rib).
# Self-contained: creates its own thirdparty first.

POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"RibCorp","client":1}
HTTP 200
[Captures]
socid: jsonpath "$"

# no bank account yet -> upstream 404
GET {{host}}/api/thirdparties/{{socid}}/bankaccounts
DOLAPIKEY: {{api_key}}
Accept: application/json
HTTP 404

# unknown third party cannot receive a bank account (upstream message)
POST {{host}}/api/thirdparties/999999/bankaccounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
Accept: application/json
{"label":"x"}
HTTP 404
[Asserts]
jsonpath "$.error.message" == "Error creating Company Bank account, Company doesn't exists"

# create one
POST {{host}}/api/thirdparties/{{socid}}/bankaccounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"label":"Main","iban":"FR7630001007941234567890185","bic":"AGRIFRPP"}
HTTP 200
[Captures]
ribid: jsonpath "$.id"
[Asserts]
jsonpath "$.socid" == {{socid}}
jsonpath "$.label" == "Main"
jsonpath "$.iban" == "FR7630001007941234567890185"
jsonpath "$.rum" contains "RUM-"

# list now contains it
GET {{host}}/api/thirdparties/{{socid}}/bankaccounts
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$" count == 1
jsonpath "$[0].id" == {{ribid}}

# deleting via a different thirdparty id -> 403 verbatim (upstream consistency check)
POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"OtherCorp","client":1}
HTTP 200
[Captures]
othersocid: jsonpath "$"

DELETE {{host}}/api/thirdparties/{{othersocid}}/bankaccounts/{{ribid}}
DOLAPIKEY: {{api_key}}
HTTP 403
[Asserts]
jsonpath "$.error.message" == "Not allowed due to bad consistency of input data"

# real delete
DELETE {{host}}/api/thirdparties/{{socid}}/bankaccounts/{{ribid}}
DOLAPIKEY: {{api_key}}
HTTP 200

# missing key is rejected
GET {{host}}/api/thirdparties/{{socid}}/bankaccounts
HTTP 401

DELETE {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 200

DELETE {{host}}/api/thirdparties/{{othersocid}}
DOLAPIKEY: {{api_key}}
HTTP 200
