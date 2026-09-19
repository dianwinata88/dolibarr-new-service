# API entrypoint rejects requests without credentials (upstream error shape)
GET {{host}}/api
HTTP 401
[Asserts]
header "Content-Type" contains "application/json"
jsonpath "$.error.code" == 401
jsonpath "$.error.message" contains "Failed to login to API"

# Key via api_key query param (Dolibarr-compatible)
GET {{host}}/api?api_key={{api_key}}
HTTP 200
[Asserts]
header "Content-Type" contains "application/ld+json"

# Key via DOLAPIKEY query param (upstream compat)
GET {{host}}/api?DOLAPIKEY={{api_key}}
HTTP 200

# Key via DOLAPIKEY header (recommended)
GET {{host}}/api
DOLAPIKEY: {{api_key}}
HTTP 200

# Key via Authorization: Bearer fallback
GET {{host}}/api
Authorization: Bearer {{api_key}}
HTTP 200

# Wrong key is rejected with the upstream error shape
GET {{host}}/api
DOLAPIKEY: wrong-key
HTTP 401
[Asserts]
jsonpath "$.error.code" == 401

# A dolcrypt:-prefixed key is rejected like upstream (503)
GET {{host}}/api
DOLAPIKEY: dolcrypt:abc
HTTP 503
[Asserts]
jsonpath "$.error.code" == 503

# DOLAPIENTITY must match the key's entity (dev keys are entity 1)
GET {{host}}/api
DOLAPIKEY: {{api_key}}
DOLAPIENTITY: 1
HTTP 200

GET {{host}}/api
DOLAPIKEY: {{api_key}}
DOLAPIENTITY: 999
HTTP 401
