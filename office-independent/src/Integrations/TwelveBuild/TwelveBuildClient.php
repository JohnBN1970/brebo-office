<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * Server-side 12Build transport. Endpoint paths and response schemas must be
 * verified against the official Postman collection before live activation.
 */
final class TwelveBuildClient
{
    /** @var callable */
    private $transport;
    private string $apiKey;
    private string $baseUrl;
    private string $authPath;
    private string $token = '';
    private int $expiresAt = 0;
    /** @var callable */
    private $clock;

    public function __construct(string $baseUrl, string $apiKey, string $authPath, callable $transport, ?callable $clock = null)
    {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new \InvalidArgumentException('12Build requires HTTPS.');
        }
        if ($apiKey === '' || $authPath === '' || $authPath[0] !== '/') {
            throw new \InvalidArgumentException('API key and absolute authentication path are required.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->authPath = $authPath;
        $this->transport = $transport;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Auth request/response shapes are intentionally supplied by the caller
     * until the official 12Build Postman collection has been inspected.
     */
    public function authenticate(callable $buildAuthRequest, callable $parseAuthResponse): void
    {
        $request = $buildAuthRequest($this->apiKey);
        if (!is_array($request)) {
            throw new \UnexpectedValueException('Invalid authentication request.');
        }
        $response = $this->send($this->authPath, $request, []);
        $parsed = $parseAuthResponse($response);
        if (!is_array($parsed) || !is_string($parsed['token'] ?? null) || $parsed['token'] === '') {
            throw new \UnexpectedValueException('Invalid authentication response.');
        }
        $ttl = $parsed['expires_in'] ?? 3600;
        if (!is_int($ttl) || $ttl < 60 || $ttl > 3600) {
            throw new \UnexpectedValueException('Invalid token lifetime.');
        }
        $this->token = $parsed['token'];
        $this->expiresAt = ($this->clock)() + $ttl;
    }

    public function request(string $path, array $request, callable $buildAuthRequest, callable $parseAuthResponse): array
    {
        if ($this->token === '' || ($this->clock)() >= $this->expiresAt - 30) {
            $this->authenticate($buildAuthRequest, $parseAuthResponse);
        }
        return $this->send($path, $request, ['X-Auth-Token' => $this->token]);
    }

    private function send(string $path, array $request, array $headers): array
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '..') || str_contains($path, '://')) {
            throw new \InvalidArgumentException('Unsafe API path.');
        }
        $result = ($this->transport)($this->baseUrl . $path, $request, $headers);
        if (!is_array($result)) {
            throw new \UnexpectedValueException('Invalid API response.');
        }
        return $result;
    }
}
