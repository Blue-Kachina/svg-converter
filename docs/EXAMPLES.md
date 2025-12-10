# Usage Examples

This page shows practical examples for calling the API.

## Convert a simple SVG (JSON response)
```bash
curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -d '{
    "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\"><rect width=\"100\" height=\"100\" fill=\"#0099ff\"/></svg>",
    "options": { "width": 100, "height": 100 },
    "format": "json"
  }'
```

## Request raw PNG via Accept header
```bash
curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -H "Accept: image/png" \
  -d '{
    "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"64\" height=\"64\"><circle cx=\"32\" cy=\"32\" r=\"30\" fill=\"#09f\"/></svg>",
    "options": { "width": 64, "height": 64 }
  }' \
  --output output.png
```

## Get base64 only
```bash
curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -d '{
    "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"128\" height=\"64\"><rect width=\"128\" height=\"64\" fill=\"#333\"/></svg>",
    "format": "base64"
  }'
```

## Batch convert two items
```bash
curl -X POST http://localhost/api/batch-convert \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      { "svg": "<svg xmlns=\\"http://www.w3.org/2000/svg\\" width=\\"64\\" height=\\"64\\"><circle cx=\\"32\\" cy=\\"32\\" r=\\"30\\" fill=\\"#09f\\"/></svg>", "options": { "width": 64, "height": 64 } },
      { "svg": "<svg xmlns=\\"http://www.w3.org/2000/svg\\" width=\\"128\\" height=\\"64\\"><rect width=\\"128\\" height=\\"64\\" fill=\\"#333\\"/></svg>", "options": { "quality": 85 } }
    ]
  }'
```
Response (202):
```json
{ "success": true, "data": { "batch_id": "e7b1f3d1-...", "count": 2 } }
```

## Poll batch status
```bash
curl http://localhost/api/batch/e7b1f3d1-...
```

## With API key
```bash
curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d '{ "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\"><rect width=\"32\" height=\"32\" fill=\"#000\"/></svg>" }'
```

## With request signing (HMAC)
Assuming `SVG_SIGNING_ENABLED=true` and `SVG_SIGNING_SECRET` set.
```bash
TS=$(date +%s)
BODY='{"svg":"<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\"><rect width=\"16\" height=\"16\" fill=\"#f00\"/></svg>"}'
SIG=$(printf "%s.%s" "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SVG_SIGNING_SECRET" -binary | xxd -p -c 256)

curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -H "X-Signature-Timestamp: $TS" \
  -H "X-Signature: $SIG" \
  -d "$BODY"
```

See more schemas and examples in `docs/openapi.yaml`. For setup and config, see `docs/SETUP.md` and `docs/CONFIG.md`.
