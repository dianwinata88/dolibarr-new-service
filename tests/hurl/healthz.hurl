# Smoke test: liveness endpoint is public and returns JSON
GET {{host}}/healthz
HTTP 200
[Asserts]
header "Content-Type" contains "application/json"
jsonpath "$.status" == "ok"
