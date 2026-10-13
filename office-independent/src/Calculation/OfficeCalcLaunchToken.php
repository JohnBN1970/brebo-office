<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/** Calc launch v1: HMAC-SHA256 over base64url-encoded JSON payload. */
final class OfficeCalcLaunchToken {
  public static function issue(
    int $calculationId,
    int $projectId,
    int $actorId,
    string $sharedSecret,
    ?int $now = null,
  ): string {
    if ($calculationId < 1 || $projectId < 1 || $actorId < 1 || strlen($sharedSecret) < 32) {
      throw new \InvalidArgumentException('Invalid Calc launch parameters.');
    }
    $time = $now ?? time();
    $payload = [
      'v' => 1,
      'calculation_id' => $calculationId,
      'project_id' => $projectId,
      'actor_id' => $actorId,
      'exp' => $time + 120,
      'nonce' => bin2hex(random_bytes(16)),
    ];
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    return $encoded . '.' . hash_hmac('sha256', $encoded, $sharedSecret);
  }
}
