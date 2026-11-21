<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Maps already extracted spreadsheet/email/document fields to intake records.
 * No file parsing, no AI guesses, no persistence.
 */
final class OfficeResidentAddressColumnMapper {
  private const ALIASES = [
    'street' => ['straat', 'straatnaam', 'street'],
    'house_number' => ['huisnummer', 'huisnr', 'nummer', 'house_number'],
    'addition' => ['toevoeging', 'huisnummertoevoeging', 'bus', 'addition'],
    'postal_code' => ['postcode', 'post_code', 'postal_code', 'zip'],
    'city' => ['woonplaats', 'plaats', 'stad', 'city'],
    'bag_id' => ['nummeraanduiding_id', 'bag_nummeraanduiding_id', 'bag_id'],
    'resident_name' => ['bewoner', 'bewonersnaam', 'naam_bewoner', 'resident_name'],
    'resident_phone' => ['telefoon', 'telefoonnummer', 'mobiel', 'resident_phone'],
    'resident_email' => ['email', 'e_mail', 'e-mailadres', 'resident_email'],
  ];

  public static function map(array $row): array {
    $normalized = [];
    foreach ($row as $key => $value) {
      if (!is_string($key)) continue;
      $key = mb_strtolower(trim($key), 'UTF-8');
      $key = preg_replace('/[\s-]+/u', '_', $key);
      if ($key !== '' && !array_key_exists($key, $normalized)) $normalized[$key] = $value;
    }
    $result = [];
    foreach (self::ALIASES as $field => $aliases) {
      $found = [];
      foreach ($aliases as $alias) {
        $alias = preg_replace('/[\s-]+/u', '_', $alias);
        if (array_key_exists($alias, $normalized) && trim((string)$normalized[$alias]) !== '') {
          $found[] = $normalized[$alias];
        }
      }
      // Conflicting aliases must not be silently resolved by column order.
      if (count(array_unique(array_map('strval', $found))) > 1) {
        throw new \InvalidArgumentException('Conflicting columns for ' . $field);
      }
      if ($found !== []) $result[$field] = $found[0];
    }
    return $result;
  }
}
