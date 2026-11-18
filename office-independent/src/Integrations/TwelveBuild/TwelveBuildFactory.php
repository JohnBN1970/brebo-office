<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * Build the connector using only Hostinger private configuration.
 * Caller owns request scheduling and the authenticated import workflow.
 */
final class TwelveBuildFactory
{
    public static function fromPrivateConfig(string $configPath): TwelveBuildClient
    {
        $config = TwelveBuildPrivateConfig::load($configPath);
        $login = $config['login'];
        $key = $config['api_key'];
        $transport = new TwelveBuildHttpTransport();
        return new TwelveBuildClient($config['base_url'], $key, '/tokens', $transport);
    }

    /**
     * Explicit auth callbacks avoid leaking credentials through URL parameters.
     * Confirm 12Build response shape before enabling live polling.
     */
    public static function authCallbacks(array $config): array
    {
        $login = $config['login'] ?? '';
        if (!is_string($login) || $login === '') {
            throw new \InvalidArgumentException('Missing 12Build login.');
        }
        return [
            static fn (string $key): array => TwelveBuildAuthentication::request($login, $key),
            static fn (array $response): array => TwelveBuildAuthentication::parse($response),
        ];
    }
}
