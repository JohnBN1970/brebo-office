<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Pure, non-persisting intake normalizer for address + optional resident data.
 * Caller must supply a parsed record from mail/document/Excel extraction.
 * No automatic resident writes, no identity guesses, no BAG lookup.
 */
final class OfficeResidentAddressProposal {
  public static function fromRecord(array $record, string $sourceType, string $sourceId): array {
    if (!in_array($sourceType, ['email', 'xlsx', 'csv', 'pdf', 'docx', 'manual'], true)
      || trim($sourceId) === '') {
      throw new \InvalidArgumentException('Source identity required');
    }
    $read = static function(string $key, int $limit) use ($record): ?string {
      $value = $record[$key] ?? null;
      if ($value === null || $value === '') return null;
      if (!is_string($value) && !is_int($value)) throw new \InvalidArgumentException('Invalid field: ' . $key);
      $value = trim((string)$value);
      if (strlen($value) > $limit) throw new \InvalidArgumentException('Field too long: ' . $key);
      return $value === '' ? null : $value;
    };
    $postcode = $read('postal_code', 16);
    if ($postcode !== null) {
      $postcode = strtoupper(preg_replace('/\s+/', '', $postcode));
      if (!preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postcode)) {
        throw new \InvalidArgumentException('Invalid Dutch postal code');
      }
    }
    $street = $read('street', 255);
    $number = $read('house_number', 16);
    $addition = $read('addition', 32);
    $city = $read('city', 160);
    $bagId = $read('bag_id', 16);
    if ($bagId !== null && !preg_match('/^[0-9]{16}$/', $bagId)) {
      throw new \InvalidArgumentException('Invalid BAG number designation');
    }
    $resident = [
      'name' => $read('resident_name', 160),
      'phone' => $read('resident_phone', 40),
      'email' => $read('resident_email', 254),
    ];
    if ($resident['email'] !== null && !filter_var($resident['email'], FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Invalid resident email');
    }
    if ($street === null || $number === null || $postcode === null) {
      return ['status' => 'needs_review', 'reason' => 'incomplete_address', 'source' => ['type' => $sourceType, 'id' => $sourceId],
        'address' => compact('street', 'number', 'addition', 'postcode', 'city', 'bagId'), 'resident' => $resident];
    }
    $key = $bagId !== null ? 'bag:' . $bagId : 'address:' . implode('|', [
      mb_strtolower($street, 'UTF-8'), $number, mb_strtolower($addition ?? '', 'UTF-8'), $postcode,
      mb_strtolower($city ?? '', 'UTF-8'),
    ]);
    return [
      'status' => 'proposal', 'match_key' => $key, 'source' => ['type' => $sourceType, 'id' => $sourceId],
      'address' => compact('street', 'number', 'addition', 'postcode', 'city', 'bagId'),
      'resident' => $resident, 'resident_write_allowed' => false,
    ];
  }
}
