<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Turns a worksheet's cell grid into candidate day entries.
 * Does not infer calendar year, worker IDs, project IDs or split allocations.
 */
final class OnSiteExcelWeekRowExtractor {
  /**
   * @param array<string,string> $cells Excel address => displayed value
   * @param array<string,string> $comments Excel address => original comment
   * @param array<string,string> $dayColumns English weekday => Excel column
   * @return array{entries:list<array<string,mixed>>,issues:list<array<string,mixed>>}
   */
  public function extract(
    string $sheet, array $cells, array $comments, array $dayColumns,
    string $personColumn, string $projectColumn, int $firstDataRow
  ): array {
    if ($firstDataRow < 1 || $firstDataRow > 100000) throw new \InvalidArgumentException('Invalid header row.');
    foreach (array_merge([$personColumn, $projectColumn], array_values($dayColumns)) as $column) {
      if (!preg_match('/^[A-Z]{1,3}$/D', $column)) throw new \InvalidArgumentException('Invalid Excel column.');
    }
    $maxRow = 0;
    foreach (array_keys($cells) as $address) {
      if (preg_match('/^[A-Z]{1,3}([1-9][0-9]*)$/D', $address, $m)) {
        $maxRow = max($maxRow, (int)$m[1]);
      }
    }
    $entries = [];
    $issues = [];
    for ($row = $firstDataRow; $row <= $maxRow; $row++) {
      $person = trim($cells[$personColumn.$row] ?? '');
      $project = trim($cells[$projectColumn.$row] ?? '');
      if ($person === '' && $project === '') continue;
      foreach ($dayColumns as $day => $column) {
        $address = $column.$row;
        $raw = trim($cells[$address] ?? '');
        $note = trim($comments[$address] ?? '');
        if ($raw === '') {
          if ($note !== '') $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'comment_without_day_hours','comment'=>$note];
          continue;
        }
        if (!is_numeric($raw)) {
          $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'non_numeric_hours','raw'=>$raw,'comment'=>$note];
          continue;
        }
        if ($person === '') {
          $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'missing_person','comment'=>$note];
          continue;
        }
        $entries[] = ['sheet'=>$sheet,'cell'=>$address,'person'=>$person,'project_label'=>$project,
          'weekday'=>$day,'hours'=>$raw,'comment'=>$note];
      }
    }
    return ['entries'=>$entries,'issues'=>$issues];
  }
}
