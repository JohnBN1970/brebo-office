<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Double-submit CSRF guard for the Office-test Calc launch POST.
 * The endpoint must additionally enforce same-origin Origin, authenticated
 * Office session, HTTPS, POST-only and an explicit launch feature flag.
 */
final class OfficeCalcLaunchCsrf
{
    public static function verify(mixed $formToken, mixed $cookieToken): bool
    {
        return is_string($formToken)
            && is_string($cookieToken)
            && preg_match('/^[a-f0-9]{64}$/D', $formToken) === 1
            && preg_match('/^[a-f0-9]{64}$/D', $cookieToken) === 1
            && hash_equals($cookieToken, $formToken);
    }

    public static function validOrigin(mixed $origin, mixed $host): bool
    {
        if (!is_string($origin) || !is_string($host) || $host === '') {
            return false;
        }
        $expected = 'https://' . $host;
        return hash_equals($expected, $origin);
    }
}
