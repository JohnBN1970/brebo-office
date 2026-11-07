<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Calc v1 assertion encoder. The caller MUST authorize the operator,
 * project and calculation before invoking this class. Never put the
 * resulting token in a URL, log, or browser storage.
 */
final class OfficeCalcLaunchToken
{
    public static function issue(
        int $calculationId,
        int $projectId,
        int $actorId,
        string $secret,
        int $now,
        ?string $grantRef = null
    ): string {
        if ($calculationId <= 0 || $projectId <= 0 || $actorId <= 0) {
            throw new \InvalidArgumentException('Invalid Calc launch identity');
        }
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Calc signing secret unavailable');
        }
        if ($now <= 0) {
            throw new \InvalidArgumentException('Invalid launch time');
        }
        if ($grantRef !== null && !preg_match('/^[a-f0-9]{64}$/D', $grantRef)) {
            throw new \InvalidArgumentException('Invalid Office grant reference');
        }

        $payload = [
            'v' => 1,
            'calculation_id' => $calculationId,
            'project_id' => $projectId,
            'actor_id' => $actorId,
            'exp' => $now + 90,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        if ($grantRef !== null) {
            $payload['grant_ref'] = $grantRef;
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        return $encoded . '.' . hash_hmac('sha256', $encoded, $secret);
    }
}
