<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Compares full website request IDs between independent staging and a
 * separately exported canonical CRM inventory. Read-only, no writes.
 */
final class WebsiteCrmCutoverAudit {

  /**
   * @param list<string> $stagingRequestIds
   * @param list<string> $canonicalRequestIds
   * @return array{missing_in_crm:list<string>,missing_in_staging:list<string>,duplicate_staging:list<string>,duplicate_crm:list<string>}
   */
  public function compare(array $stagingRequestIds, array $canonicalRequestIds): array {
    $staging = $this->normalize($stagingRequestIds);
    $canonical = $this->normalize($canonicalRequestIds);
    return [
      'missing_in_crm' => array_values(array_diff(array_keys($staging['counts']), array_keys($canonical['counts']))),
      'missing_in_staging' => array_values(array_diff(array_keys($canonical['counts']), array_keys($staging['counts']))),
      'duplicate_staging' => $staging['duplicates'],
      'duplicate_crm' => $canonical['duplicates'],
    ];
  }

  /** @param list<string> $ids
   * @return array{counts:array<string,int>,duplicates:list<string>}
   */
  private function normalize(array $ids): array {
    $counts = [];
    foreach ($ids as $id) {
      if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
        throw new \InvalidArgumentException('Audit requires full website request UUIDs.');
      }
      $key = strtolower($id);
      $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    ksort($counts);
    return [
      'counts' => $counts,
      'duplicates' => array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)),
    ];
  }
}
