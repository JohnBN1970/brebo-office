<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * 12Build authentication mapping. Endpoint confirmed as POST /tokens.
 * Keep response parsing isolated until the response schema is verified.
 */
final class TwelveBuildAuthentication
{
    public static function request(string $login, string $apiKey): array
    {
        if ($login === '' || $apiKey === '') {
            throw new \InvalidArgumentException('12Build login and API key are required.');
        }
        return ['method' => 'POST', 'json' => ['login' => $login, 'apiKey' => $apiKey]];
    }

    public static function parse(array $response): array
    {
        $token = $response['token'] ?? $response['accessToken'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \UnexpectedValueException('12Build token missing; verify response schema.');
        }
        return ['token' => $token, 'expires_in' => 3600];
    }
}
