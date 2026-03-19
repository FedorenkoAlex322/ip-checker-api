# IP Checker API

Highload REST API for checking IP addresses, domains, and emails against multiple threat intelligence providers. Built with resilience patterns: circuit breaker, retry with exponential backoff, rate limiting, and multi-layer caching.

## Features

- **IP/Domain/Email Lookup** — aggregate threat data from multiple providers
- **Rate Limiting** — Redis sliding window, per API key, tier-based (Free/Pro/Enterprise)
- **Caching** — Redis with TTL + stale-while-revalidate for zero-downtime
- **Retry Logic** — exponential backoff with jitter, configurable per provider
- **Circuit Breaker** — Closed/Open/HalfOpen state machine, prevents cascade failures
- **API Keys & Quotas** — SHA-256 hashed keys, daily/monthly usage tracking
- **Logging & Metrics** — structured JSON logging, request audit trail
- **Mock Provider** — deterministic fake data for development and testing

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL 8.0 |
| Cache/Queue/State | Redis 7 |
| Web Server | Nginx |
| Containers | Docker + docker-compose |
| Static Analysis | PHPStan (Larastan) level 6 |
| Code Style | Laravel Pint (PSR-12) |
| Tests | PHPUnit (87 tests, 368 assertions) |

## Quick Start (Docker)

```bash
# Clone the repository
git clone https://github.com/FedorenkoAlex322/ip-checker-api.git
cd ip-checker-api

# Copy environment file
cp .env.docker .env

# Start all services
docker-compose up -d --build

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Seed demo API keys (plaintext keys will be displayed once)
docker-compose exec app php artisan db:seed
```

The API is available at `http://localhost:8000`.

## Quick Start (Local)

Prerequisites: PHP 8.3+, Composer, MySQL, Redis.

```bash
# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Configure .env: DB_*, REDIS_*, CACHE_STORE=redis, QUEUE_CONNECTION=redis

# Run migrations and seed
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve

# Start queue worker (separate terminal)
php artisan queue:work redis --sleep=3 --tries=3
```

## API Endpoints

All endpoints (except health) require `X-API-Key` header.

### Lookup

```
POST /api/v1/lookup/ip        — Check IP address
POST /api/v1/lookup/domain    — Check domain
POST /api/v1/lookup/email     — Check email
GET  /api/v1/lookup/history   — Lookup history (paginated)
GET  /api/v1/lookup/{uuid}    — Get result by UUID
```

### Other

```
GET  /api/v1/quota            — Current quota usage
GET  /api/v1/health           — Health check (no auth)
```

## Usage Examples

### Check an IP address

```bash
curl -X POST http://localhost:8000/api/v1/lookup/ip \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"target": "8.8.8.8"}'
```

Response:
```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "target": "8.8.8.8",
    "type": "ip",
    "result": {
      "ip": "8.8.8.8",
      "risk_score": 12,
      "is_vpn": false,
      "is_proxy": false,
      "country": "US",
      "isp": "Mock ISP Inc.",
      "blacklists": []
    },
    "cached": false
  },
  "meta": {
    "provider": "mock",
    "lookup_time_ms": 52.3,
    "cached": false
  }
}
```

### Check quota

```bash
curl http://localhost:8000/api/v1/quota \
  -H "X-API-Key: YOUR_API_KEY"
```

### Health check

```bash
curl http://localhost:8000/api/v1/health
```

## Response Headers

Rate limit information is included in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1704067200
```

## Error Responses

All errors follow a consistent format:

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Try again in 30 seconds.",
    "retry_after": 30
  }
}
```

| Code | HTTP Status | Description |
|------|------------|-------------|
| `VALIDATION_ERROR` | 422 | Invalid request data |
| `INVALID_API_KEY` | 401 | Missing or invalid API key |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `QUOTA_EXCEEDED` | 429 | Daily/monthly quota exceeded |
| `PROVIDER_UNAVAILABLE` | 503 | All providers are down |
| `CIRCUIT_BREAKER_OPEN` | 503 | Circuit breaker is open |
| `LOOKUP_NOT_FOUND` | 404 | Lookup result not found |

## API Key Tiers

| Tier | Requests/min | Daily Limit | Monthly Limit |
|------|-------------|-------------|---------------|
| Free | 60 | 1,000 | 10,000 |
| Pro | 300 | 50,000 | 1,000,000 |
| Enterprise | 1,000 | 500,000 | 10,000,000 |

## Architecture

```
Request
  │
  ├─ ForceJsonResponse
  ├─ AuthenticateApiKey (X-API-Key → SHA-256 → DB lookup)
  ├─ CheckQuota (Redis counter → daily/monthly limits)
  ├─ RateLimitByApiKey (Redis sorted set → sliding window)
  └─ RequestLogger (terminable, logs after response)
        │
        ▼
    Controller (thin)
        │
        ▼
    LookupService (orchestrator)
        │
        ├─ Cache hit? → return immediately
        │
        ├─ ProviderRegistry → select by priority + circuit breaker
        │     │
        │     ▼
        │   RetryService (exponential backoff + jitter)
        │     │
        │     ▼
        │   Provider.lookup() → LookupResult DTO
        │
        ├─ Success → cache, store, dispatch LookupCompleted event
        │
        └─ All failed → stale cache fallback → or 503
```

### Key Design Patterns

- **Strategy** — pluggable lookup providers via `LookupProviderInterface`
- **Circuit Breaker** — Redis-backed state machine with WATCH/MULTI/EXEC
- **Stale-While-Revalidate** — serve stale cache, refresh async via job
- **Repository** — data access abstraction for API keys and results
- **Service Layer** — business logic separated from controllers
- **Event-Driven** — async logging and quota tracking via Laravel events

## Project Structure

```
app/
├── Contracts/          # 9 interfaces (DI contracts)
├── DTOs/               # 6 immutable value objects
├── Enums/              # 4 backed enums
├── Events/             # 5 domain events
├── Exceptions/         # 8 custom exceptions (JSON rendering)
├── Http/
│   ├── Controllers/Api/V1/   # 3 thin controllers
│   ├── Middleware/            # 5 middleware (auth, quota, rate limit, logging)
│   ├── Requests/             # 3 form requests + base class
│   └── Resources/            # 4 API resources
├── Jobs/               # 3 queued jobs (cache refresh, quota sync, pruning)
├── Listeners/          # 7 event listeners
├── Models/             # 5 Eloquent models
├── Repositories/       # 2 Eloquent repositories
└── Services/
    ├── ApiKey/         # Key generation, validation
    ├── Cache/          # Redis lookup cache + stale
    ├── CircuitBreaker/ # State machine (Redis)
    ├── Lookup/         # Core orchestrator
    ├── Providers/      # Abstract + Mock + Registry
    ├── Quota/          # Redis + MySQL quota tracking
    ├── RateLimiter/    # Sliding window (Lua script)
    └── Retry/          # Exponential backoff + jitter

config/
├── ip-checker.php      # Providers, cache TTL, retention
├── rate-limiting.php   # Tier limits
└── circuit-breaker.php # Thresholds, retry config
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# With coverage (requires Xdebug/PCOV)
php artisan test --coverage
```

Test breakdown: 31 unit + 50 feature + 6 integration = **87 tests, 368 assertions**.

## Code Quality

```bash
# Static analysis (PHPStan level 6)
vendor/bin/phpstan analyse --memory-limit=512M

# Code style (Laravel Pint / PSR-12)
vendor/bin/pint

# Check style without fixing
vendor/bin/pint --test
```

## Scheduled Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `SyncQuotaToDatabaseJob` | Every 5 minutes | Flush Redis quota counters to MySQL |
| `PruneOldLookupsJob` | Daily at 3:00 AM | Remove logs/results older than retention period |

Enable the scheduler:
```bash
# Add to crontab
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

## Environment Variables

See [`.env.example`](.env.example) for all available configuration options. Key variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | `mysql` | Database driver |
| `CACHE_STORE` | `redis` | Cache backend |
| `QUEUE_CONNECTION` | `redis` | Queue backend |
| `RATE_LIMIT_FREE` | `60` | Free tier: requests/minute |
| `RATE_LIMIT_PRO` | `300` | Pro tier: requests/minute |
| `RATE_LIMIT_ENTERPRISE` | `1000` | Enterprise tier: requests/minute |
| `CIRCUIT_BREAKER_THRESHOLD` | `5` | Failures before circuit opens |
| `CIRCUIT_BREAKER_RECOVERY_TIME` | `30` | Seconds before half-open |
| `CACHE_TTL_IP_LOOKUP` | `3600` | IP lookup cache TTL (seconds) |
| `PROVIDER_MOCK_ENABLED` | `true` | Enable mock provider |

## Adding a Real Provider

1. Create a class extending `AbstractLookupProvider`:

```php
final class AbuseIpDbProvider extends AbstractLookupProvider
{
    public function getName(): string { return 'abuseipdb'; }
    public function getPriority(): int { return 10; }

    protected function supportedTypes(): array { return [LookupType::Ip]; }

    protected function doLookup(string $target, LookupType $type): array
    {
        // Call external API, return structured data
    }
}
```

2. Register in `config/ip-checker.php`:

```php
'providers' => [
    'abuseipdb' => [
        'enabled' => env('PROVIDER_ABUSEIPDB_ENABLED', false),
        'class' => \App\Services\Providers\AbuseIpDbProvider::class,
        'priority' => 10,
        'supports' => ['ip'],
        'api_key' => env('PROVIDER_ABUSEIPDB_KEY'),
    ],
],
```

3. The provider is automatically registered via `AppServiceProvider` and selected by `ProviderRegistry` based on priority and circuit breaker state.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
