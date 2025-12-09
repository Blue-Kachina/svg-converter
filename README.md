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



## Notes
- **Dependencies Already Included**: Guzzle HTTP, Faker for testing, and Symfony components are already available in composer setup
- **Queue Processing**: Redis 
- **Testing**: PHPUnit and Mockery are ready for use


