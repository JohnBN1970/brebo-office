<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Verify a server-to-server signed intake payload before JSON decoding.
 * Transport MUST be HTTPS and upstream must enforce request size/rate limits.
 * Source secrets must be injected from isolated Office-test configuration.
 */
final class OfficeIntakeSourceAuthenticator
{
    /**
     * @param array<string,string> $secrets Source name to independent secret
     */
    public function __construct(private readonly array $secrets)
    {
    }

    /**
     * @throws \DomainException on any invalid authentication metadata
     */
    public function verify(
        string $source,
        string $rawBody,
        string $timestamp,
        string $nonce,
        string $signature,
        int $now
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $source)
            || !isset($this->secrets[$source])
            || !is_string($this->secrets[$source])
            || strlen($this->secrets[$source]) < 32) {
            throw new \DomainException('intake_source_not_authorized');
        }
        if (!preg_match('/^[0-9]{10}$/D', $timestamp)
            || abs($now - (int) $timestamp) > 300) {
            throw new \DomainException('intake_signature_expired');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)
            || !preg_match('/^sha256=[a-f0-9]{64}$/D', $signature)) {
            throw new \DomainException('intake_signature_invalid');
        }
        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $source . "\n" . $timestamp . "\n" . $nonce . "\n" . $rawBody,
            $this->secrets[$source]
        );
        if (!hash_equals($expected, $signature)) {
            throw new \DomainException('intake_signature_invalid');
        }
    }
}
