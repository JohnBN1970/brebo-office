<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\ObjectExceptionLineRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseObjectExceptionLineRepository implements ObjectExceptionLineRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function context(int $applicationObjectId): ?array {
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->fields('o');
    $query->addField('a', 'subcalculation_id');
    $query->addField('s', 'calculation_id');
    $row = $query->condition('o.id', $applicationObjectId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function editableContext(int $applicationObjectId): ?array {
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->join('brebo_calculation_version', 'v', 'v.calculation_id = s.calculation_id AND v.version = s.version');
    $query->fields('o');
    $query->addField('a', 'locked_at', 'application_locked_at');
    $query->addField('s', 'calculation_id');
    $query->addField('s', 'status', 'subcalculation_status');
    $query->addField('s', 'locked_at', 'subcalculation_locked_at');
    $query->addField('v', 'status', 'version_status');
    $query->addField('v', 'locked_at', 'version_locked_at');
    $row = $query->condition('o.id', $applicationObjectId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function insertLine(array $values): int {
    return (int) $this->database->insert('brebo_calculation_subcalculation_object_exception_line')->fields($values)->execute();
  }

  public function markApplicationObjectException(int $applicationObjectId): void {
    $this->database->update('brebo_calculation_subcalculation_application_object')
      ->fields(['is_exception' => 1])
      ->condition('id', $applicationObjectId)
      ->execute();
  }

  public function lines(int $applicationObjectId): array {
    return $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l')
      ->fields('l', ['quantity', 'labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost'])
      ->condition('application_object_id', $applicationObjectId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function nextSortOrder(int $applicationObjectId): int {
    $query = $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l');
    $query->addExpression('MAX(sort_order)', 'max_order');
    $max = $query->condition('application_object_id', $applicationObjectId)->execute()->fetchField();
    return ((int) $max) + 10;
  }
}
