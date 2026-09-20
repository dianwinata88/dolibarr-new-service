# Golden path: categories (llx_categorie) — CRUD + object link/unlink.

POST {{host}}/api/categories
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"label":"HurlCat-{{newUuid}}","type":2}
HTTP 200
[Captures]
catid: jsonpath "$"
[Asserts]
jsonpath "$" isInteger

GET {{host}}/api/categories/{{catid}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.label" startsWith "HurlCat-"
jsonpath "$.type" == 2

# category types dictionary
GET {{host}}/api/categories/types
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.customer" == "ProspectsOrCustomers"

# link a thirdparty to the category (upstream PUT on thirdparties side)
POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"CatCorp","client":1}
HTTP 200
[Captures]
socid: jsonpath "$"

PUT {{host}}/api/thirdparties/{{socid}}/categories/{{catid}}
DOLAPIKEY: {{api_key}}
HTTP 200

GET {{host}}/api/thirdparties/{{socid}}/categories
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$[0].id" == {{catid}}

# objects listing under the category
GET {{host}}/api/categories/{{catid}}/objects?type=customer
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$[0].id" == {{socid}}

# unlink
DELETE {{host}}/api/thirdparties/{{socid}}/categories/{{catid}}
DOLAPIKEY: {{api_key}}
HTTP 200

DELETE {{host}}/api/categories/{{catid}}
DOLAPIKEY: {{api_key}}
HTTP 200
