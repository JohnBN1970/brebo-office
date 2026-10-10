<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Resolves only explicitly reviewed legacy-to-Office ID mappings.
 * Never treats equal numeric IDs as equivalent across systems.
 */
final class WebsiteCrmLegacyReferenceResolver {

  /**
   * @param list<array{legacy_node_id:int,field:string,target_type:string,target_id:int}> $references
   * @param array<string,array<int|string,int|string>> $approvedMappings
   * @return array{resolved:list<array<string,int|string>>,unresolved:list<array<string,int|string>>}
   */
  public function resolve(array $references, array $approvedMappings): array {
    $resolved = [];
    $unresolved = [];
    foreach ($references as $reference) {
      $type = $reference['target_type'];
      $legacyId = $reference['target_id'];
      if ($type === 'unresolved' || !isset($approvedMappings[$type])
        || !array_key_exists($legacyId, $approvedMappings[$type])) {
        $unresolved[] = $reference;
        continue;
      }
      $officeId = $approvedMappings[$type][$legacyId];
      if ((!is_int($officeId) && !is_string($officeId))
        || trim((string) $officeId) === '' || (string) $officeId === '0') {
        throw new \InvalidArgumentException('Invalid approved Office CRM mapping.');
      }
      $resolved[] = $reference + ['office_target_id' => $officeId];
    }
    return ['resolved' => $resolved, 'unresolved' => $unresolved];
  }

  /**
   * Rejects cutover while any reference is unmapped.
   *
   * @param array{resolved:list<mixed>,unresolved:list<mixed>} $result
   */
  public function assertReadyForCutover(array $result): void {
    if ($result['unresolved'] !== []) {
      throw new \RuntimeException('CRM cutover blocked: unresolved legacy references.');
    }
  }
}
