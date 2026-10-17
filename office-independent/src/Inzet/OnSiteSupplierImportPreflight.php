<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only preflight for supplier Excel imports. Produces candidate labels
 * and quality warnings; never creates people, projects or invoice approval.
 */
final class OnSiteSupplierImportPreflight {
  /**
   * @param list<array<string,mixed>> $rows Normalized day rows from Excel
   * @param list<array<string,string>> $extractionIssues
   * @return array<string,mixed>
   */
  public static function inspect(array $rows, array $extractionIssues = []): array {
    $people = [];
    $projects = [];
    $warnings = [];
    $minutes = 0;
    foreach ($rows as $row) {
      $sheet = (string)($row['sheet'] ?? '');
      $cell = (string)($row['cell'] ?? '');
      $person = trim((string)($row['person_label'] ?? ''));
      $project = trim((string)($row['project_label'] ?? ''));
      $value = $row['minutes'] ?? null;
      if ($person !== '') $people[$person] = true;
      if ($project !== '') $projects[$project] = true;
      if (!is_int($value) || $value < 0 || $value > 1440) {
        $warnings[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'invalid_minutes'];
        continue;
      }
      $minutes += $value;
      if ($person === '') $warnings[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'person_unresolved'];
      if ($project === '') $warnings[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'project_unresolved'];
      if (($row['review_required'] ?? false) === true || trim((string)($row['comment'] ?? '')) !== '') {
        $warnings[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'manual_source_review'];
      }
    }
    foreach ($extractionIssues as $issue) {
      $warnings[] = [
        'sheet'=>(string)($issue['sheet'] ?? ''),
        'cell'=>(string)($issue['cell'] ?? ''),
        'reason'=>(string)($issue['reason'] ?? 'extraction_issue'),
      ];
    }
    $personLabels = array_keys($people);
    $projectLabels = array_keys($projects);
    sort($personLabels, SORT_STRING);
    sort($projectLabels, SORT_STRING);
    return [
      'row_count'=>count($rows),
      'person_label_count'=>count($personLabels),
      'project_label_count'=>count($projectLabels),
      'person_labels'=>$personLabels,
      'project_labels'=>$projectLabels,
      'total_minutes_unverified'=>$minutes,
      'warnings'=>$warnings,
      'warning_count'=>count($warnings),
      'status'=>'review_required',
      'approved'=>false,
      'invoice_eligible'=>false,
    ];
  }
}
