# Golden path: customer pricing & discounts
# (llx_societe_remise / llx_societe_remise_except / llx_societe_prices).

POST {{host}}/api/thirdparties
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"name":"PriceCorp","client":1}
HTTP 200
[Captures]
socid: jsonpath "$"

# --- fixed amount discounts ---

POST {{host}}/api/thirdparties/{{socid}}/fixedamountdiscounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"amount":100,"description":"Gift","tva_tx":20,"vat_src_code":"SRC"}
HTTP 201
[Captures]
discountid: jsonpath "$"
[Asserts]
jsonpath "$" isInteger

GET {{host}}/api/thirdparties/{{socid}}/fixedamountdiscounts
DOLAPIKEY: {{api_key}}
HTTP 200
[Asserts]
jsonpath "$[0].description" == "Gift"
jsonpath "$[0].amount_ttc" == 120

# available discounts for an invoice (upstream shape, all available here)
GET {{host}}/api/thirdparties/{{socid}}/availablediscounts
DOLAPIKEY: {{api_key}}
HTTP 200

# split the 120 TTC discount into 50 + 70
POST {{host}}/api/thirdparties/{{socid}}/splitdiscount/{{discountid}}
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"amount_ttc_1":50,"amount_ttc_2":70}
HTTP 200
[Asserts]
jsonpath "$" count == 2

# mismatched split -> 405 like upstream
POST {{host}}/api/thirdparties/{{socid}}/fixedamountdiscounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"amount":10,"description":"Gift2"}
HTTP 201
[Captures]
discountid2: jsonpath "$"

POST {{host}}/api/thirdparties/{{socid}}/splitdiscount/{{discountid2}}
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"amount_ttc_1":5,"amount_ttc_2":99}
HTTP 405

# --- relative discounts (llx_societe_remise) ---

GET {{host}}/api/thirdparties/{{socid}}/relativediscounts
DOLAPIKEY: {{api_key}}
HTTP 200

POST {{host}}/api/thirdparties/{{socid}}/relativediscounts
DOLAPIKEY: {{api_key}}
Content-Type: application/json
{"remise":10,"note":"loyalty"}
HTTP 201

# --- setpricelevel is gated: product module + multiprices off -> 501 ---

PUT {{host}}/api/thirdparties/{{socid}}/setpricelevel/2
DOLAPIKEY: {{api_key}}
HTTP 501
[Asserts]
jsonpath "$.error.code" == 501
jsonpath "$.error.message" contains "needed for this request"

DELETE {{host}}/api/thirdparties/{{socid}}
DOLAPIKEY: {{api_key}}
HTTP 200
