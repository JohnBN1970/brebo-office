<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Validate device-originated automatic presence evidence.
 * Authentication and assignment authorization remain caller responsibilities.
 */
final class OnSiteAutomaticEventValidator {
  public static function validate(array $payload, DateTimeImmutable $receivedAt): array {
    foreach (['event_id','project_id','zone_id','kind','occurred_at'] as $field) {
      if (!is_string($payload[$field] ?? null) || trim($payload[$field]) === '') {
        throw new InvalidArgumentException('Missing automatic event field');
      }
    }
    if (!preg_match('/^[a-f0-9-]{36}$/iD', $payload['event_id'])) {
      throw new InvalidArgumentException('Invalid event identifier');
    }
    if (!in_array($payload['kind'], ['in','out'], true)) {
      throw new InvalidArgumentException('Invalid event type');
    }
    if (!ctype_digit($payload['project_id']) || !ctype_digit($payload['zone_id'])
      || (int)$payload['project_id'] < 1 || (int)$payload['zone_id'] < 1) {
      throw new InvalidArgumentException('Invalid project or zone');
    }
    try { $occurred = new DateTimeImmutable($payload['occurred_at']); }
    catch (\Throwable) { throw new InvalidArgumentException('Invalid event time'); }
    $difference = $occurred->getTimestamp() - $receivedAt->getTimestamp();
    if ($difference > 300 || $difference < -604800) {
      throw new InvalidArgumentException('Event outside accepted delivery window');
    }
    if (array_key_exists('latitude', $payload) || array_key_exists('longitude', $payload)) {
      throw new InvalidArgumentException('Coordinates not accepted');
    }
    return [
      'event_id'=>strtolower($payload['event_id']),
      'project_id'=>$payload['project_id'],
      'zone_id'=>$payload['zone_id'],
      'kind'=>$payload['kind'],
      'occurred_at'=>$occurred->format(DATE_ATOM),
    ];
  }
}
