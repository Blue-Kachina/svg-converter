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
  }
}
```

- `svg` (required): SVG XML string.
- `options` (optional):
  - `width` (int, 1-8192)
  - `height` (int, 1-8192)
  - `density` (int, 1-1200)
  - `background` (string, e.g. `#ffffff`)
  - `quality` (int, 1-100)

Response 200 (application/json):
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


## Notes
- **Dependencies Already Included**: Guzzle HTTP, Faker for testing, and Symfony components are already available in composer setup
- **Queue Processing**: Redis 
- **Testing**: PHPUnit and Mockery are ready for use

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
