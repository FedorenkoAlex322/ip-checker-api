# IP Checker API

Highload REST API для проверки IP-адресов и доменов с акцентом на устойчивость, производительность и отказоустойчивость при работе с внешними API.

## Tech Stack

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Framework | Laravel 13 | REST API backend |
| PHP | 8.3+ | Strict typing, modern features |
| Database | MySQL | Primary data store |
| Cache/State | Redis | Caching, rate limiting, circuit breaker state, queue driver |
| Infrastructure | Docker + docker-compose | Containerization |
| Testing | Pest PHP / PHPUnit | Unit + Feature + Integration tests |

## Architecture Overview

### Core Concepts

API принимает запросы на проверку IP/доменов, агрегирует данные из внешних провайдеров, кэширует результаты, и обеспечивает отказоустойчивость через circuit breaker + retry logic.

### Key Patterns

- **Service Layer** — бизнес-логика проверки IP/доменов
- **Strategy Pattern** — подключаемые внешние API-провайдеры (mock + real)
- **Circuit Breaker** — защита от каскадных отказов внешних API
- **Repository Pattern** — для API keys, quotas, lookup history
- **API Resources** — стандартизированные JSON-ответы
- **Form Requests** — валидация входных данных
- **Jobs/Queue** — async обработка тяжёлых проверок

### Application Layers

```
Request → Middleware (Auth + RateLimit) → Controller → Service → ExternalAPI
                                                         ↓
                                              Cache (Redis, TTL)
                                                         ↓
                                              CircuitBreaker → Retry (exp. backoff)
                                                         ↓
                                              Response (API Resource)
```

## Features Checklist

### Rate Limiting (per API key)
- Redis-backed rate limiter
- Configurable limits per tier (free/pro/enterprise)
- Sliding window algorithm
- Rate limit headers in response (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset)

### Caching
- Redis cache with configurable TTL per endpoint
- Cache invalidation (manual + TTL-based)
- Cache-Control headers
- Stale-while-revalidate pattern for highload

### Retry Logic
- Exponential backoff with jitter
- Configurable max retries per provider
- Retry only on transient errors (5xx, timeout, connection reset)

### Circuit Breaker
- States: Closed → Open → Half-Open
- Failure threshold configurable per provider
- Recovery timeout
- Redis-backed state persistence
- Fallback responses when circuit is open

### API Keys & Quotas
- API key generation and management
- Per-key quota tracking (daily/monthly)
- Tier-based access (free/pro/enterprise)
- Usage statistics

### Logging & Metrics
- Structured JSON logging
- Request/response logging (sanitized)
- External API call metrics (latency, success rate, error rate)
- Circuit breaker state changes

## External API Providers

Внешние API для проверки IP/доменов. Реализация через интерфейс с mock и real имплементациями.

```php
interface IpLookupProviderInterface
{
    public function lookup(string $target): LookupResult;
    public function supports(string $type): bool; // 'ip' | 'domain'
    public function getName(): string;
}
```

Провайдеры согласуются в процессе разработки. Mock-провайдер доступен для тестирования.

## Project Structure (Target)

```
app/
├── Http/
│   ├── Controllers/Api/       # API controllers
│   ├── Middleware/             # Rate limiting, API auth
│   ├── Requests/              # Form Request validation
│   └── Resources/             # API Resources (JSON responses)
├── Models/                    # Eloquent models
├── Services/
│   ├── IpLookup/              # Core lookup service + providers
│   ├── CircuitBreaker/        # Circuit breaker implementation
│   ├── RateLimiter/           # Custom rate limiter
│   └── Cache/                 # Cache management
├── Jobs/                      # Async lookup jobs
├── Events/                    # Lookup events, circuit breaker events
├── Enums/                     # CircuitState, LookupType, ApiKeyTier
└── Exceptions/                # Custom exceptions
config/
├── ip-checker.php             # App-specific config
├── rate-limiting.php          # Rate limit tiers config
└── circuit-breaker.php        # Circuit breaker config
database/
├── migrations/                # Schema
├── factories/                 # Test factories
└── seeders/                   # Demo data
routes/
└── api.php                    # API routes (versioned: /api/v1/*)
tests/
├── Feature/                   # API endpoint tests
├── Unit/                      # Service/logic tests
└── Integration/               # External API integration tests
```

## Database Tables (Target)

- `api_keys` — API ключи с tier, rate limits, active status
- `api_key_usages` — usage tracking (daily/monthly quotas)
- `lookup_results` — cached lookup results
- `lookup_logs` — request audit log
- `circuit_breaker_states` — circuit breaker state persistence (backup to Redis)

## API Endpoints (Target)

```
POST   /api/v1/lookup/ip          # Check IP address
POST   /api/v1/lookup/domain      # Check domain
GET    /api/v1/lookup/{id}        # Get lookup result by ID
GET    /api/v1/lookup/history     # Lookup history for API key

GET    /api/v1/quota              # Current quota usage
GET    /api/v1/health             # Health check (services + circuit breakers)
```

### Authentication
All endpoints require `X-API-Key` header.

### Response Format
```json
{
  "data": { ... },
  "meta": {
    "cached": true,
    "cache_ttl": 3600,
    "provider": "mock",
    "lookup_time_ms": 42
  }
}
```

### Error Format
```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Try again in 30 seconds.",
    "retry_after": 30
  }
}
```

## Development Commands

```bash
# Start dev environment
composer dev              # Laravel serve + queue + vite

# Run tests
composer test             # PHPUnit
php artisan test          # Laravel test runner

# Code quality
./vendor/bin/pint         # Laravel Pint (code style)
./vendor/bin/phpstan      # Static analysis

# Database
php artisan migrate       # Run migrations
php artisan db:seed       # Seed test data
php artisan migrate:fresh --seed  # Reset + seed
```

## Environment Variables (Required)

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ip_checker_api
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Cache & Queue via Redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Rate Limiting defaults
RATE_LIMIT_FREE=60           # requests per minute (free tier)
RATE_LIMIT_PRO=300           # requests per minute (pro tier)
RATE_LIMIT_ENTERPRISE=1000   # requests per minute (enterprise tier)

# Circuit Breaker
CIRCUIT_BREAKER_THRESHOLD=5        # failures before opening
CIRCUIT_BREAKER_RECOVERY_TIME=30   # seconds before half-open

# Cache TTL
CACHE_TTL_IP_LOOKUP=3600      # 1 hour
CACHE_TTL_DOMAIN_LOOKUP=3600  # 1 hour

# External API providers (TBD)
# IP_PROVIDER_1_URL=
# IP_PROVIDER_1_KEY=
```

## Code Standards

- `declare(strict_types=1)` в каждом PHP файле
- PSR-12 code style (enforced via Laravel Pint)
- PHPStan level 8+
- Return type declarations на всех методах
- Property type hints на всех свойствах
- Form Requests для валидации
- API Resources для response formatting
- Meaningful naming (English)
- DocBlocks только где type system недостаточен

## Testing Strategy

- **Unit tests**: Services, CircuitBreaker, RateLimiter, Retry logic
- **Feature tests**: API endpoints, middleware, auth
- **Integration tests**: External API providers (with mocks)
- Coverage target: 85%+
- Не мокать базу данных — реальная тестовая БД (SQLite in-memory)
- Mock только внешние HTTP-вызовы

## Git Workflow

- Feature branches от `main`
- Conventional commits
- PR с code review перед merge
