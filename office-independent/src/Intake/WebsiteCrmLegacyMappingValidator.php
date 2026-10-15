<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Validates an operator-reviewed mapping inventory before reference remapping.
 * An Office record cannot be silently reused for two different legacy records.
 */
final class WebsiteCrmLegacyMappingValidator {

  /**
   * @param array<string,array<int|string,int|string>> $mappings
   * @return array<string,array<int|string,int|string>>
   */
  public function validate(array $mappings): array {
    $normalized = [];
    foreach ($mappings as $type => $ids) {
      if (!is_string($type) || $type === '' || $type === 'unresolved' || !is_array($ids)) {
        throw new \InvalidArgumentException('Invalid CRM mapping entity type.');
      }
      $targets = [];
      foreach ($ids as $legacyId => $officeId) {
        if ((!is_int($legacyId) && !(is_string($legacyId) && ctype_digit($legacyId)))
          || (int) $legacyId < 1 || (!is_int($officeId) && !is_string($officeId))
          || trim((string) $officeId) === '' || (string) $officeId === '0') {
          throw new \InvalidArgumentException('Invalid legacy-to-Office CRM mapping.');
        }
        $targetKey = (string) $officeId;
        if (isset($targets[$targetKey])) {
          throw new \InvalidArgumentException('Two legacy CRM records map to the same Office record.');
        }
        $targets[$targetKey] = true;
        $normalized[$type][(int) $legacyId] = $officeId;
      }
    }
    return $normalized;
  }
}
