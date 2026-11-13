<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Calc v2 payload contract for CRM-first drafts, project_id intentionally null.
 * This encoder does not authorize anything: callers MUST validate current CRM
 * edit rights and mint the grant_ref in the Office database first.
 */
final class OfficeCalcCrmSignedLaunch
{
    public static function issue(int $draftId, int $actorId, string $grantRef, string $secret, int $now): string
    {
        if ($draftId < 1 || $actorId < 1 || $now < 1
            || strlen($secret) < 32
            || preg_match('/^[a-f0-9]{64}$/D', $grantRef) !== 1) {
            throw new \InvalidArgumentException('Invalid CRM Calc v2 launch');
        }
        $payload = [
            'v' => 2,
            'grant_ref' => $grantRef,
            'calculation_id' => $draftId,
            'project_id' => null,
            'actor_id' => (string)$actorId,
            'exp' => $now + 90,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        if (strlen($encoded) > 4000) throw new \InvalidArgumentException('CRM Calc launch too large');
        return $encoded.'.'.hash_hmac('sha256', $encoded, $secret);
    }
}
