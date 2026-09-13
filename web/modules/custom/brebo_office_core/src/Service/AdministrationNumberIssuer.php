<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Atomically issues immutable document numbers per administration and series. */
final class AdministrationNumberIssuer {

  private const COLLECTION = 'brebo_office_core.numbering';

  public function __construct(
    private readonly AdministrationRegistry $administrations,
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Issues or returns the existing immutable number for one owner.
   *
   * The owner tuple makes the call idempotent: retrying for the same entity or
   * document never consumes a second number.
   *
   * @return array<string, mixed>
   */
  public function issue(string $administrationCode, string $series, string $ownerType, string $ownerId, ?int $year = NULL): array {
    $administration = $this->administrations->get($administrationCode);
    $definition = $administration['numbering'][$series] ?? NULL;
    if (!is_array($definition)) {
      throw new \InvalidArgumentException(sprintf('Onbekende nummerreeks %s voor administratie %s.', $series, $administrationCode));
    }

    $ownerType = trim($ownerType);
    $ownerId = trim($ownerId);
    if ($ownerType === '' || $ownerId === '') {
      throw new \InvalidArgumentException('Owner type en owner id zijn verplicht voor nummeruitgifte.');
    }

    $store = $this->keyValue->get(self::COLLECTION);
    $ownerKey = sprintf('issued:%s:%s:%s:%s', $administrationCode, $series, $ownerType, $ownerId);
    $existing = $store->get($ownerKey);
    if (is_array($existing) && isset($existing['number'])) {
      return $existing;
    }

    $issueYear = $year ?? (int) gmdate('Y');
    $bucket = !empty($definition['reset_yearly']) ? (string) $issueYear : 'all';
    $lockName = sprintf('brebo_number:%s:%s:%s', $administrationCode, $series, $bucket);
    if (!$this->lock->acquire($lockName, 10.0)) {
      throw new \RuntimeException('Nummerreeks is tijdelijk bezet. Probeer opnieuw.');
    }

    try {
      // Re-check after acquiring the lock to make concurrent retries idempotent.
      $existing = $store->get($ownerKey);
      if (is_array($existing) && isset($existing['number'])) {
        return $existing;
      }

      $cursorKey = sprintf('cursor:%s:%s:%s', $administrationCode, $series, $bucket);
      $start = max(1, (int) ($definition['start_number'] ?? 1));
      $lastIssued = (int) $store->get($cursorKey, $start - 1);
      $sequence = max($start, $lastIssued + 1);
      $number = $this->formatNumber($definition, $sequence, $issueYear);

      $numberKey = sprintf('number:%s:%s:%s', $administrationCode, $series, $number);
      if ($store->get($numberKey) !== NULL) {
        throw new \LogicException(sprintf('Nummer %s is reeds uitgegeven; cursor is inconsistent.', $number));
      }

      $receipt = [
        'administration_code' => $administrationCode,
        'series' => $series,
        'number' => $number,
        'sequence' => $sequence,
        'year' => $issueYear,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'issued_at' => gmdate(DATE_ATOM),
        'definition_snapshot' => [
          'prefix' => (string) ($definition['prefix'] ?? ''),
          'include_year' => !empty($definition['include_year']),
          'separator' => (string) ($definition['separator'] ?? '-'),
          'digits' => max(1, (int) ($definition['digits'] ?? 4)),
          'start_number' => $start,
          'reset_yearly' => !empty($definition['reset_yearly']),
        ],
      ];

      // Persist receipt before moving the cursor; the number is never reusable.
      $store->set($ownerKey, $receipt);
      $store->set($numberKey, $receipt);
      $store->set($cursorKey, $sequence);
      return $receipt;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /** @param array<string, mixed> $definition */
  private function formatNumber(array $definition, int $sequence, int $year): string {
    $separator = (string) ($definition['separator'] ?? '-');
    $parts = [];
    $prefix = trim((string) ($definition['prefix'] ?? ''));
    if ($prefix !== '') {
      $parts[] = $prefix;
    }
    if (!empty($definition['include_year'])) {
      $parts[] = (string) $year;
    }
    $parts[] = str_pad((string) $sequence, max(1, (int) ($definition['digits'] ?? 4)), '0', STR_PAD_LEFT);
    return implode($separator, $parts);
  }

}
