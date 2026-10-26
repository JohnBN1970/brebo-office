<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Detects daily-hour columns from Excel header cells, not fixed column letters.
 * Input is supplied by an XLSX reader; formulas/totals are never day entries.
 */
final class OnSiteExcelWeekSheetDetector {
  /** @param array<string,mixed> $headers @return array<string,string> */
  public function dayColumns(array $headers): array {
    $aliases = [
      'maandag'=>'monday','monday'=>'monday','dinsdag'=>'tuesday','tuesday'=>'tuesday',
      'woensdag'=>'wednesday','wednesday'=>'wednesday','donderdag'=>'thursday',
      'thursday'=>'thursday','vrijdag'=>'friday','friday'=>'friday',
      'zaterdag'=>'saturday','saturday'=>'saturday','zondag'=>'sunday','sunday'=>'sunday',
    ];
    $found = [];
    foreach ($headers as $column => $raw) {
      if (!is_string($column) || !preg_match('/^[A-Z]{1,3}$/D', $column)) {
        continue;
      }
      $label = strtolower(trim((string)$raw));
      if (isset($aliases[$label])) {
        $day = $aliases[$label];
        if (isset($found[$day])) {
          throw new \DomainException('Dubbele dagkolom in urenstaat.');
        }
        $found[$day] = $column;
      }
    }
    return $found;
  }
}
