<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\SubcalculationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseSubcalculationRepository implements SubcalculationRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function subcalculations(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_subcalculation', 's')->fields('s')->condition('calculation_id', $calculationId)->condition('version', $version)->orderBy('label')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function applications(int $subcalculationId): array {
    return $this->database->select('brebo_calculation_subcalculation_application', 'a')->fields('a')->condition('subcalculation_id', $subcalculationId)->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function structure(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_structure', 's')->fields('s')->condition('calculation_id', $calculationId)->condition('version', $version)->orderBy('sort_order')->orderBy('depth')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rowDomains(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')->condition('calculation_id', $calculationId)->condition('version', $version)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function scopeCount(int $subcalculationId): int {
    return (int) $this->database->select('brebo_calculation_subcalculation_scope', 'ss')->condition('subcalculation_id', $subcalculationId)->countQuery()->execute()->fetchField();
  }

  public function applicationCount(int $subcalculationId): int {
    return (int) $this->database->select('brebo_calculation_subcalculation_application', 'a')->condition('subcalculation_id', $subcalculationId)->countQuery()->execute()->fetchField();
  }

  public function applicationForSubcalculation(int $applicationId, int $subcalculationId): ?array {
    $row=$this->database->select('brebo_calculation_subcalculation_application', 'a')->fields('a')->condition('id', $applicationId)->condition('subcalculation_id', $subcalculationId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function applicationObjectsDetailed(int $applicationId): array {
    return $this->database->select('brebo_calculation_subcalculation_application_object', 'o')->fields('o')->condition('application_id', $applicationId)->orderBy('object_type')->orderBy('object_ref')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function exceptionObjectCount(int $applicationId): int {
    return (int) $this->database->select('brebo_calculation_subcalculation_application_object', 'o')->condition('application_id', $applicationId)->condition('is_exception', 1)->countQuery()->execute()->fetchField();
  }

  public function deleteScope(int $scopeId): void {
    $this->database->delete('brebo_calculation_subcalculation_scope')->condition('id', $scopeId)->execute();
  }

  public function insertSubcalculation(array $values): int {
    return (int) $this->database->insert('brebo_calculation_subcalculation')->fields($values)->execute();
  }

  public function insertScope(array $values): int {
    return (int) $this->database->insert('brebo_calculation_subcalculation_scope')->fields($values)->execute();
  }

  public function insertApplication(array $values): int {
    return (int) $this->database->insert('brebo_calculation_subcalculation_application')->fields($values)->execute();
  }

  public function insertApplicationObject(array $values): int {
    return (int) $this->database->insert('brebo_calculation_subcalculation_application_object')->fields($values)->execute();
  }

  public function application(int $applicationId): ?array {
    $row = $this->database->select('brebo_calculation_subcalculation_application', 'a')
      ->fields('a', ['subcalculation_id', 'locked_at', 'quantity'])
      ->condition('id', $applicationId)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function subcalculation(int $subcalculationId): ?array {
    $row = $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s')
      ->condition('id', $subcalculationId)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function scopes(int $subcalculationId): array {
    return $this->database->select('brebo_calculation_subcalculation_scope', 'ss')
      ->fields('ss', ['scope_type', 'scope_ref', 'multiplier'])
      ->condition('subcalculation_id', $subcalculationId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }


  public function selectedScopes(int $subcalculationId): array {
    return $this->database->select('brebo_calculation_subcalculation_scope', 'ss')
      ->fields('ss', ['id', 'scope_type', 'scope_ref'])
      ->condition('subcalculation_id', $subcalculationId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rowIdsForParagraph(int $calculationId, string $version, string $paragraphKey): array {
    return array_map('intval', $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['row_id'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute()->fetchCol());
  }

  public function rowCosts(int $calculationId, string $version, int $rowId): ?array {
    $row = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('row_id', $rowId)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function applicationObjects(int $applicationId): array {
    return $this->database->select('brebo_calculation_subcalculation_application_object', 'o')
      ->fields('o', ['id', 'factor', 'exception_labour', 'exception_material', 'exception_equipment', 'exception_subcontracting', 'exception_other'])
      ->condition('application_id', $applicationId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function exceptionLines(array $applicationObjectIds): array {
    if ($applicationObjectIds === []) return [];
    $query = $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l');
    $query->fields('l', ['application_object_id', 'quantity', 'labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost']);
    $query->condition('application_object_id', $applicationObjectIds, 'IN');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function isEditableVersion(int $calculationId, string $version): bool {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return (bool) ($row && $row['status'] === 'draft' && $row['locked_at'] === NULL);
  }

  public function scopeExists(int $calculationId, string $version, string $scopeType, string $scopeRef): bool {
    if ($scopeType === 'line') {
      $exists = $this->database->select('brebo_calculation_row_domain', 'r')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('row_id', (int) $scopeRef)
        ->countQuery()->execute()->fetchField();
    } else {
      $exists = $this->database->select('brebo_calculation_structure', 's')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('node_key', $scopeRef)
        ->countQuery()->execute()->fetchField();
    }
    return (bool) $exists;
  }

  public function nextScopeOrder(int $subcalculationId): int {
    $query = $this->database->select('brebo_calculation_subcalculation_scope', 's');
    $query->addExpression('MAX(sort_order)', 'max_order');
    $max = $query->condition('subcalculation_id', $subcalculationId)->execute()->fetchField();
    return ((int) $max) + 10;
  }
}
