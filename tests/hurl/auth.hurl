# API entrypoint rejects requests without credentials
GET {{host}}/api
HTTP 401

# Same key via api_key query param (Dolibarr-compatible)
GET {{host}}/api?api_key={{api_key}}
HTTP 200
[Asserts]
header "Content-Type" contains "application/ld+json"

# Same key via DOLAPIKEY header
GET {{host}}/api
DOLAPIKEY: {{api_key}}
HTTP 200

# Wrong key is rejected
GET {{host}}/api
DOLAPIKEY: wrong-key
HTTP 401
