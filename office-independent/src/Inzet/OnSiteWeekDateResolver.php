<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Resolves an explicit ISO week/year and weekday to a calendar date.
 * The reporting year is mandatory: a sheet named "wk35" does not encode it.
 */
final class OnSiteWeekDateResolver {
  public function weekNumber(string $sheetName): ?int {
    if (!preg_match('/^(?:week|wk)\\s*0?([1-9]|[1-4][0-9]|5[0-3])$/iD', trim($sheetName), $m)) {
      return null;
    }
    return (int)$m[1];
  }

  public function date(int $isoYear, int $isoWeek, string $weekday): string {
    if ($isoYear < 2000 || $isoYear > 2100 || $isoWeek < 1 || $isoWeek > 53) {
      throw new \InvalidArgumentException('Ongeldig ISO-jaar of weeknummer.');
    }
    $days = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,
      'friday'=>5,'saturday'=>6,'sunday'=>7];
    $day = $days[strtolower(trim($weekday))] ?? null;
    if ($day === null) throw new \InvalidArgumentException('Onbekende weekdag.');
    $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setISODate($isoYear,$isoWeek,$day);
    if ((int)$date->format('o') !== $isoYear || (int)$date->format('W') !== $isoWeek) {
      throw new \InvalidArgumentException('Week bestaat niet in dit ISO-jaar.');
    }
    return $date->format('Y-m-d');
  }

  /**
   * @param list<array<string,mixed>> $rows
   * @return array{rows:list<array<string,mixed>>,issues:list<array<string,string>>}
   */
  public function attachDates(array $rows, int $isoYear): array {
    $resolved = [];
    $issues = [];
    foreach ($rows as $row) {
      $sheet = (string)($row['sheet'] ?? '');
      $week = $this->weekNumber($sheet);
      if ($week === null) {
        $issues[] = ['sheet'=>$sheet,'cell'=>(string)($row['cell'] ?? ''),'reason'=>'week_number_unresolved'];
        continue;
      }
      try {
        $row['date'] = $this->date($isoYear,$week,(string)($row['weekday'] ?? ''));
        $resolved[] = $row;
      } catch (\InvalidArgumentException $exception) {
        $issues[] = ['sheet'=>$sheet,'cell'=>(string)($row['cell'] ?? ''),'reason'=>'invalid_week_or_weekday'];
      }
    }
    return ['rows'=>$resolved,'issues'=>$issues];
  }
}
