<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Verifies the exact signedOfficeHeaders contract from Calc officeTransport.ts.
 * Caller must separately enforce HTTPS, trusted host, JSON body size and
 * one-time request ID replay protection before authorizing a grant.
 */
final class OfficeCalcSignedRequest
{
    public static function verify(
        string $method,
        string $path,
        string $rawBody,
        string $timestamp,
        string $requestId,
        string $signature,
        string $sharedSecret,
        int $now
    ): bool {
        if ($method !== 'POST'
            || $path !== '/api/workbench/v1/calculation-grants/validate'
            || strlen($rawBody) > 8192
            || strlen($sharedSecret) < 32
            || !preg_match('/^[0-9]{10}$/D', $timestamp)
            || abs($now - (int)$timestamp) > 90
            || !preg_match('/^[a-f0-9-]{36}$/iD', $requestId)
            || !preg_match('/^v1=[a-f0-9]{64}$/D', $signature)) {
            return false;
        }
        $canonical = implode("\n", [
            $method,
            $path,
            hash('sha256', $rawBody),
            $timestamp,
            $requestId,
        ]);
        $expected = 'v1=' . hash_hmac('sha256', $canonical, $sharedSecret);
        return hash_equals($expected, $signature);
    }
}
