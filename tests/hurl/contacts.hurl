# Golden path: contacts (llx_socpeople) — CRUD + thirdparty linkage.

POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"ContactCorp","client":1}
HTTP 200
[Captures]
socid: jsonpath "$"

POST {{host}}/api/contacts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"firstname":"Jane","lastname":"Doe","socid":{{socid}},"email":"jane.doe.{{newUuid}}@example.com"}
HTTP 200
[Captures]
contactid: jsonpath "$"
[Asserts]
jsonpath "$" isInteger

GET {{host}}/api/contacts/{{contactid}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Captures]
cemail: jsonpath "$.email"
[Asserts]
jsonpath "$.id" == {{contactid}}
jsonpath "$.lastname" == "Doe"
jsonpath "$.socid" == {{socid}}

# upstream lookup by email
GET {{host}}/api/contacts/email/{{cemail}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.id" == {{contactid}}

# update
PUT {{host}}/api/contacts/{{contactid}}
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"lastname":"Smith","poste":"CTO"}
HTTP 200
[Asserts]
jsonpath "$.lastname" == "Smith"

# thirdparty contacts listing
GET {{host}}/api/thirdparties/{{socid}}/contacts
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$[0].id" == {{contactid}}

# delete then 404
DELETE {{host}}/api/contacts/{{contactid}}
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$.success.code" == 200

GET {{host}}/api/contacts/{{contactid}}
DOLAPIKEY: {{api_key}}
HTTP 404
