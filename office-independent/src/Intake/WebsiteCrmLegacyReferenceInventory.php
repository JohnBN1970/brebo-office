<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Inventories legacy Drupal entity references without guessing new IDs.
 * References remain unresolved until their destination records are mapped.
 */
final class WebsiteCrmLegacyReferenceInventory {

  /** @param list<array<string,mixed>> $opportunities
   * @return list<array{legacy_node_id:int,field:string,target_type:string,target_id:int}>
   */
  public function collect(array $opportunities): array {
    $references = [];
    foreach ($opportunities as $row) {
      $sourceId = $row['legacy_node_id'] ?? null;
      if (!is_int($sourceId) || $sourceId < 1) {
        throw new \InvalidArgumentException('Invalid source opportunity ID.');
      }
      $fields = $row['fields'] ?? [];
      if (!is_array($fields)) {
        throw new \InvalidArgumentException('Invalid source CRM fields.');
      }
      foreach ($fields as $name => $values) {
        if (!is_string($name) || !is_array($values)) {
          continue;
        }
        foreach ($values as $value) {
          if (!is_array($value) || !array_key_exists('target_id', $value)) {
            continue;
          }
          $id = $value['target_id'];
          if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new \InvalidArgumentException('Invalid CRM entity reference target.');
          }
          $id = (int) $id;
          if ($id < 1) {
            throw new \InvalidArgumentException('Invalid CRM entity reference ID.');
          }
          $references[] = [
            'legacy_node_id' => $sourceId,
            'field' => $name,
            'target_type' => $this->targetType($name),
            'target_id' => $id,
          ];
        }
      }
    }
    return $references;
  }

  private function targetType(string $field): string {
    return match ($field) {
      'field_brebo_opp_contact_ref' => 'brebo_contact',
      'field_brebo_opp_organization_ref' => 'brebo_organization',
      'field_brebo_opp_offer_ref' => 'offer',
      'field_brebo_opp_owner' => 'user',
      default => 'unresolved',
    };
  }
}
