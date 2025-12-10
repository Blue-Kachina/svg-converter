# SVG Converter
## Description
This is a Laravel-based microservice.
It can accept SVGs via its API, and it returns a base64-encoded-PNG string
It's currently dev-only, and is set up using Sail.  Includes redis container


#### POST /api/convert
- Method: `POST`
- Path: `/api/convert`
- Content-Type: `application/json`

Request body:
```json
{
  "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\"><rect width=\"100\" height=\"100\" fill=\"#09f\"/></svg>",
  "options": {
    "width": 100,
    "height": 100,
    "density": 300,
    "background": "#ffffff",
    "quality": 90
  },
  "format": "json"
}
```

- `svg` (required): SVG XML string.
- `options` (optional):
  - `width` (int, 1-8192)
  - `height` (int, 1-8192)
  - `density` (int, 1-1200)
  - `background` (string, e.g. `#ffffff`)
  - `quality` (int, 1-100)
- `format` (optional):
  - `json` (default): returns JSON with base64 string
  - `png`: returns raw PNG bytes (`Content-Type: image/png`); can also be requested via header `Accept: image/png`
  - `base64`: returns plain-text base64 string (`Content-Type: text/plain; charset=utf-8`)

Response 200 (application/json when format=json):
```json
{
  "success": true,
  "data": {
    "png_base64": "iVBORw0KGgoAAAANSUhEUgAA..." 
  },
  "meta": {
    "duration_ms": 42,
    "content_length": 12345
  }
}
```

When `format=png` or `Accept: image/png`, the response is raw PNG with headers:
- `Content-Type: image/png`
- `Content-Disposition: inline; filename="image.png"`
- `X-Content-Type-Options: nosniff`
- `Cache-Control: no-store`

Errors:
- 422 VALIDATION_ERROR — malformed input or invalid options.
- 400 CONVERSION_FAILED — conversion error (invalid svg or exceeds limits).
- 500 INTERNAL_ERROR — unexpected server error.


##### Manual Testing
I've been testing using the following from the command line
```
curl -X POST http://localhost/api/convert \
  -H "Content-Type: application/json" \
  -d '{
    "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\"><rect width=\"100\" height=\"100\" fill=\"#0099ff\"/></svg>"
  }'
```


## Performance & Load Testing
- Benchmark conversion speed and memory in-process:
  - `sail artisan svg:benchmark --iterations=100 --width=256 --height=256 --density=300 --quality=90 --warmup=5`
- Load test the /api/convert endpoint:
  - `sail artisan svg:load-test --url=http://localhost/api/convert --requests=200 --concurrency=20 --format=json`
- Tips:
  - Run inside Sail/Octane for stable numbers. For Octane: `sail artisan octane:start --server=frankenphp --workers=1 --max-requests=250`
  - Use `--format=png` to request raw PNG and exercise content negotiation.

## Notes
- **Dependencies Already Included**: Guzzle HTTP, Faker for testing, and Symfony components are already available in composer setup
- **Queue Processing**: Redis 
- **Testing**: PHPUnit and Mockery are ready for use

## Security & Rate Limiting
- Default rate limits (can be tuned via `config/svg.php` or env):
  - `POST /api/convert`: 60/min per IP/user (env `SVG_RATE_LIMIT_CONVERT`)
  - `POST /api/batch-convert`: 15/min (env `SVG_RATE_LIMIT_BATCH`)
  - `GET /api/status` and misc diagnostics: 120/min (env `SVG_RATE_LIMIT_STATUS`)
- Optional API key auth:
  - Enable via `SVG_API_AUTH_ENABLED=true`
  - Provide keys via `SVG_API_KEYS=key1,key2` and header name via `SVG_API_KEY_HEADER` (default `X-API-Key`)
- Optional request signing (HMAC):
  - `SVG_SIGNING_ENABLED=true`, set `SVG_SIGNING_SECRET`
  - Client sends headers `X-Signature-Timestamp` (unix seconds) and `X-Signature` = `hex(hmac_algo, ts + '.' + raw_body, secret)`
  - Configure via: `SVG_SIGNATURE_HEADER`, `SVG_TIMESTAMP_HEADER`, `SVG_SIGNATURE_SKEW` (default 300s), `SVG_SIGNATURE_ALGO` (default `sha256`)
- Input size constraints:
  - Raw SVG max bytes enforced early: `SVG_MAX_BYTES` (default 524288)
  - Batch requests: up to 50 items; each item’s SVG is checked against `SVG_MAX_BYTES`.

### Batch Conversion API

#### POST /api/batch-convert
- Method: `POST`
- Path: `/api/batch-convert`
- Content-Type: `application/json`

Body:
```json
{
  "items": [
    {
      "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"64\" height=\"64\"><circle cx=\"32\" cy=\"32\" r=\"30\" fill=\"#09f\"/></svg>",
      "options": { "width": 64, "height": 64 }
    },
    {
      "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"128\" height=\"64\"><rect width=\"128\" height=\"64\" fill=\"#333\"/></svg>",
      "options": { "quality": 85 }
    }
  ]
}
```

Response 202:
```json
{
  "success": true,
  "data": { "batch_id": "e7b1f3d1-...", "count": 2 }
}
```

- Up to 50 items per request.
- Each item supports the same `options` as `/api/convert`.

#### GET /api/batch/{id}
- Method: `GET`
- Path: `/api/batch/{id}`

Response 200:
```json
{
  "success": true,
  "data": {
    "batch_id": "e7b1f3d1-...",
    "status": "running",
    "progress": { "total": 2, "processed": 1, "failed": 0, "pending": 1 },
    "items": {
      "0": { "status": "succeeded", "result_base64": "iVBORw0K...", "result_bytes": 1234 },
      "1": { "status": "pending" }
    },
    "meta": { "status": "started", "total": 2, "created_at": "2025-12-09T17:09:00Z" }
  }
}
```


Notes:
- Results are stored transiently in cache for quick polling. Configure your cache store appropriately in `config/cache.php`.
- The service uses Laravel Bus batches; use a queue worker (`sail artisan queue:work`) for asynchronous processing.
