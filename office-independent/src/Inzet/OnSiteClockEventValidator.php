<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Validates mobile OnSite clock-event envelopes before authorization and persistence.
 * Does not authenticate devices, authorize projects, or write events.
 */
final class OnSiteClockEventValidator {
  /** @return array{event_id:string,project_id:int,person_id:int,action:string,occurred_at:string,latitude:?float,longitude:?float,accuracy_m:?float} */
  public function validate(array $input): array {
    $eventId = $input['event_id'] ?? null;
    if (!is_string($eventId) || !preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $eventId)) {
      throw new \InvalidArgumentException('Ongeldig uniek gebeurtenisnummer.');
    }
    $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $personId = filter_var($input['person_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($projectId) || !is_int($personId)) throw new \InvalidArgumentException('Project of medewerker ontbreekt.');
    $action = $input['action'] ?? null;
    if (!in_array($action, ['in', 'out'], true)) throw new \InvalidArgumentException('Ongeldige klokactie.');
    $timestamp = $input['occurred_at'] ?? null;
    if (!is_string($timestamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)) {
      throw new \InvalidArgumentException('Kloktijd moet een tijdzone bevatten.');
    }
    try {
      $date = new \DateTimeImmutable($timestamp);
    } catch (\Exception $exception) {
      throw new \InvalidArgumentException('Ongeldige kloktijd.', 0, $exception);
    }
    if ($date->format('Y') < 2020 || $date->format('Y') > 2100) throw new \InvalidArgumentException('Kloktijd buiten bereik.');
    $lat = $this->coordinate($input, 'latitude', -90, 90);
    $lon = $this->coordinate($input, 'longitude', -180, 180);
    if (($lat === null) !== ($lon === null)) throw new \InvalidArgumentException('Locatie moet volledig zijn.');
    $accuracy = $this->coordinate($input, 'accuracy_m', 0, 100000);
    if ($accuracy !== null && $lat === null) throw new \InvalidArgumentException('Locatienauwkeurigheid zonder locatie.');
    return ['event_id' => $eventId, 'project_id' => $projectId, 'person_id' => $personId,
      'action' => $action, 'occurred_at' => $date->format('Y-m-d\TH:i:s.uP'),
      'latitude' => $lat, 'longitude' => $lon, 'accuracy_m' => $accuracy];
  }

  private function coordinate(array $input, string $field, float $min, float $max): ?float {
    if (!array_key_exists($field, $input) || $input[$field] === null) return null;
    if (!is_int($input[$field]) && !is_float($input[$field])) throw new \InvalidArgumentException('Ongeldige ' . $field);
    $value = (float) $input[$field];
    if (!is_finite($value) || $value < $min || $value > $max) throw new \InvalidArgumentException('Ongeldige ' . $field);
    return $value;
  }
}
