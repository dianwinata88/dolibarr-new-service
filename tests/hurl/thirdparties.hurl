# Golden path: third parties (llx_societe) — CRUD + lookups + linked resources.
# Self-contained: creates its own thirdparty first.

POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"HurlCorp","client":1,"email":"hurlcorp.{{newUuid}}@example.com"}
HTTP 200
[Captures]
socid: jsonpath "$"
[Asserts]
jsonpath "$" isInteger

GET {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Captures]
tpemail: jsonpath "$.email"
[Asserts]
jsonpath "$.id" == {{socid}}
jsonpath "$.name" == "HurlCorp"
jsonpath "$.status" == 1
jsonpath "$.client" == 1

# email lookup (upstream route)
GET {{host}}/api/thirdparties/email/{{tpemail}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.id" == {{socid}}

# update
PUT {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"HurlCorp2","phone":"0102030405"}
HTTP 200
[Asserts]
jsonpath "$.name" == "HurlCorp2"

# index with sqlfilters-free list
GET {{host}}/api/thirdparties?sortfield=t.rowid&sortorder=DESC&limit=5
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$[0].id" >= {{socid}}

# outstanding endpoints keep the upstream shape (empty in CRM-only domain)
GET {{host}}/api/thirdparties/{{socid}}/outstandinginvoices
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.opened" == 0
jsonpath "$.refs" count == 0

# delete then 404 like upstream
DELETE {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.success.code" == 200

GET {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 404
[Asserts]
jsonpath "$.error.code" == 404
jsonpath "$.error.message" == "Thirdparty not found"
