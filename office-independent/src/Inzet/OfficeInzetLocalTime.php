<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Converts Office's Europe/Brussels wall-clock input into unambiguous UTC.
 * Rejects nonexistent and repeated local times at daylight-saving transitions.
 */
final class OfficeInzetLocalTime {
  public static function toUtc(string $local): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$local)) {
      throw new InvalidArgumentException('Invalid local datetime');
    }
    $tz = new DateTimeZone('Europe/Brussels');
    $naive = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('UTC'));
    if ($naive === false || $naive->format('Y-m-d\TH:i') !== $local) {
      throw new InvalidArgumentException('Invalid local date');
    }
    $wall = $naive->getTimestamp();
    $matches = [];
    foreach ($tz->getTransitions($wall - 3 * 86400,$wall + 3 * 86400) as $transition) {
      $offset = (int)$transition['offset'];
      $utc = $wall - $offset;
      $candidate = (new DateTimeImmutable('@'.$utc))->setTimezone($tz);
      if ($candidate->format('Y-m-d\TH:i') === $local) $matches[$utc] = true;
    }
    if (count($matches) !== 1) throw new InvalidArgumentException('Ambiguous or nonexistent local time');
    $utc = (int)array_key_first($matches);
    return (new DateTimeImmutable('@'.$utc))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }
}
