<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;

/** Signed form token: session CSRF proof and stable request identity. */
final class OnSiteOfficeZoneFormToken {
  public static function issue(WebsiteCrmOperatorContext $actor, string $project, string $building, string $secret, int $now): string {
    $prefix = ($now + 1800) . '.' . bin2hex(random_bytes(32));
    return $prefix . '.' . self::signature($prefix, $actor, $project, $building, $secret);
  }

  public static function requestKey(mixed $token, WebsiteCrmOperatorContext $actor, string $project, string $building, string $secret, int $now): string {
    if (!is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $token, $match)
      || (int)$match[1] <= $now || (int)$match[1] > $now + 1800
      || !hash_equals(self::signature($match[1].'.'.$match[2], $actor, $project, $building, $secret), $match[3])) {
      throw new InvalidArgumentException('invalid_zone_form_token');
    }
    return hash('sha256', $token);
  }

  private static function signature(string $prefix, WebsiteCrmOperatorContext $actor, string $project, string $building, string $secret): string {
    if (strlen($secret) < 32 || !preg_match('/^[1-9][0-9]{0,18}$/D', $project)
      || !preg_match('/^[1-9][0-9]{0,18}$/D', $building)) {
      throw new InvalidArgumentException('invalid_zone_form_context');
    }
    return hash_hmac('sha256', json_encode(['office-building-zone:v1', $prefix,
      $actor->operatorId, $actor->sessionId, $project, $building], JSON_THROW_ON_ERROR), $secret);
  }
}
