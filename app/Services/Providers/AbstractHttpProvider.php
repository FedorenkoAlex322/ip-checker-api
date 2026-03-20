<?php

namespace App\Services\Providers;

use App\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

abstract class AbstractHttpProvider extends AbstractLookupProvider
{
    /**
     * Config key for this provider (e.g., 'abuseipdb', 'virustotal').
     */
    abstract protected function getConfigKey(): string;

    /**
     * Whether this provider requires an API key to function.
     */
    abstract public function requiresApiKey(): bool;

    /**
     * Get a config value for this provider.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return config("ip-checker.providers.{$this->getConfigKey()}.{$key}", $default);
    }

    /**
     * Get API key from config.
     */
    public function getApiKey(): ?string
    {
        $key = $this->config('api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Get API key or throw if not configured.
     *
     * @throws ProviderException
     */
    protected function getApiKeyOrFail(): string
    {
        return $this->getApiKey() ?? throw new ProviderException(
            $this->getName(),
            'API key is required but not configured',
        );
    }

    /**
     * Get base URL from config.
     */
    protected function getBaseUrl(): string
    {
        return rtrim((string) $this->config('base_url', ''), '/');
    }

    /**
     * Get timeout in seconds from config.
     */
    protected function getTimeout(): int
    {
        return (int) $this->config('timeout', 10);
    }

    public function getPriority(): int
    {
        return (int) $this->config('priority', 50);
    }

    /**
     * Provider is enabled if API key is not required, or key is present.
     */
    public function isEnabled(): bool
    {
        if (! $this->requiresApiKey()) {
            return true;
        }

        return $this->getApiKey() !== null;
    }

    /**
     * Default headers for HTTP requests. Override in subclasses to add auth headers.
     *
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /**
     * Make an HTTP GET request with standardized error handling.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    protected function httpGet(string $url, array $query = []): array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->withHeaders($this->getHeaders())
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new ProviderException(
                $this->getName(),
                "Connection failed: {$e->getMessage()}",
                $e,
            );
        }

        if ($response->status() === 429) {
            throw new ProviderException(
                $this->getName(),
                'Rate limit exceeded by external API',
            );
        }

        if ($response->serverError()) {
            throw new ProviderException(
                $this->getName(),
                "Server error: HTTP {$response->status()}",
            );
        }

        if ($response->clientError()) {
            throw new ProviderException(
                $this->getName(),
                "Client error: HTTP {$response->status()}",
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new ProviderException(
                $this->getName(),
                'Invalid JSON response',
            );
        }

        return $json;
    }
}
