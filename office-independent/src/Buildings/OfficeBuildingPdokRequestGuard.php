<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;

/** Session-secret HMAC: bound to building, mount and expiry, no extra cookie. */
final class OfficeBuildingPdokRequestGuard {
  public static function token(string $sessionSecret, int $buildingId, string $mount, int $now): string {
    if (strlen($sessionSecret) < 32 || $buildingId < 1) throw new \InvalidArgumentException('invalid_session');
    $expires = $now + 900;
    return $expires . '.' . hash_hmac('sha256', "$mount:bag-save:$buildingId:$expires", $sessionSecret);
  }

  public static function verify(array $server, mixed $token, string $sessionSecret, int $buildingId, string $mount, string $publicHost, int $now): void {
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST'
      || ($server['HTTP_ORIGIN'] ?? '') !== 'https://' . $publicHost
      || ($server['HTTP_HOST'] ?? '') !== $publicHost
      || empty($server['HTTPS']) || $server['HTTPS'] === 'off'
      || !is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/D', $token, $m)
      || (int)$m[1] < $now || (int)$m[1] > $now + 900
      || strlen($sessionSecret) < 32 || $buildingId < 1
      || !hash_equals(hash_hmac('sha256', "$mount:bag-save:$buildingId:$m[1]", $sessionSecret), $m[2])) {
      throw new \DomainException('invalid_pdok_request');
    }
  }
}
