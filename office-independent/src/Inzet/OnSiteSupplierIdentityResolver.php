<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Explicitly approved supplier-name mappings only. Never fuzzy-match identities.
 * Names may recur across suppliers; mappings are scoped to supplier_id.
 */
final class OnSiteSupplierIdentityResolver {
  /**
   * @param list<array<string,mixed>> $rows normalized Excel rows
   * @param array<string,int> $people person label => Office person ID, confirmed for this supplier
   * @param array<string,int> $projects project label => Office project ID, confirmed for this supplier
   * @return array{rows:list<array<string,mixed>>,issues:list<array<string,string>>}
   */
  public function resolve(array $rows, int $supplierId, array $people, array $projects): array {
    if ($supplierId < 1) throw new \InvalidArgumentException('Supplier ID is required.');
    $resolved = [];
    $issues = [];
    foreach ($rows as $row) {
      $person = trim((string)($row['person_label'] ?? ''));
      $project = trim((string)($row['project_label'] ?? ''));
      $sheet = (string)($row['sheet'] ?? '');
      $cell = (string)($row['cell'] ?? '');
      $personId = $people[$person] ?? null;
      $projectId = $projects[$project] ?? null;
      if (!is_int($personId) || $personId < 1 || !is_int($projectId) || $projectId < 1) {
        $issues[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>
          (!is_int($personId) || $personId < 1) ? 'person_mapping_unresolved' : 'project_mapping_unresolved'];
        continue;
      }
      if (($row['review_required'] ?? false) === true) {
        $issues[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'source_note_requires_review'];
        continue;
      }
      if (!isset($row['date']) || !is_string($row['date']) || !isset($row['minutes']) || !is_int($row['minutes'])) {
        $issues[] = ['sheet'=>$sheet,'cell'=>$cell,'reason'=>'invalid_date_or_minutes'];
        continue;
      }
      $resolved[] = ['supplier_id'=>$supplierId,'person_id'=>$personId,'project_id'=>$projectId,
        'date'=>$row['date'],'minutes'=>$row['minutes'],'source_sheet'=>$sheet,'source_cell'=>$cell];
    }
    return ['rows'=>$resolved,'issues'=>$issues];
  }
}
