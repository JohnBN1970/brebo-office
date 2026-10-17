<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Normalizes pre-extracted Excel cells to reviewable day rows.
 * Extraction adapter must supply cell comments separately from displayed values.
 * Unknown project labels are never silently mapped to Office project IDs.
 */
final class OnSiteWeeklyTimesheetImport {
  /**
   * @param list<array{sheet:string,cell:string,person:string,project_label:string,weekday:string,hours:mixed,comment?:string}> $cells
   * @return array{rows:list<array<string,mixed>>,issues:list<array<string,string>>}
   */
  public function normalize(array $cells): array {
    $rows = [];
    $issues = [];
    foreach ($cells as $cell) {
      $sheet = trim((string)($cell['sheet'] ?? ''));
      $address = trim((string)($cell['cell'] ?? ''));
      $person = trim((string)($cell['person'] ?? ''));
      $project = trim((string)($cell['project_label'] ?? ''));
      $weekday = trim((string)($cell['weekday'] ?? ''));
      $comment = trim((string)($cell['comment'] ?? ''));
      $hours = $cell['hours'] ?? null;
      if ($person === '' || $sheet === '' || $address === '' || $weekday === '') {
        $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'missing_identity_or_day'];
        continue;
      }
      if (!is_int($hours) && !is_float($hours) && !(is_string($hours) && is_numeric($hours))) {
        $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'non_numeric_hours'];
        continue;
      }
      $numeric = (float)$hours;
      if (!is_finite($numeric) || $numeric < 0 || $numeric > 24) {
        $issues[] = ['sheet'=>$sheet,'cell'=>$address,'reason'=>'hours_out_of_range'];
        continue;
      }
      $minutes = (int)round($numeric * 60);
      $rows[] = [
        'sheet'=>$sheet,'cell'=>$address,'person_label'=>$person,
        'project_label'=>$project,'weekday'=>$weekday,'minutes'=>$minutes,
        'comment'=>$comment,
        'review_required'=>$project === '' || $comment !== '',
        'review_reason'=>$project === '' ? 'project_unresolved' : ($comment !== '' ? 'comment_requires_interpretation' : ''),
      ];
    }
    return ['rows'=>$rows,'issues'=>$issues];
  }
}
