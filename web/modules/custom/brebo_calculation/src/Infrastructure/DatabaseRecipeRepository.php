<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\RecipeRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseRecipeRepository implements RecipeRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function publishedVersion(int $recipeVersionId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_version', 'rv')->fields('rv')->condition('id', $recipeVersionId)->condition('status', 'published')->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function recipe(int $recipeId): ?array {
    $row = $this->database->select('brebo_calculation_recipe', 'r')->fields('r')->condition('id', $recipeId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function parameters(int $recipeVersionId): array {
    return $this->database->select('brebo_calculation_recipe_parameter', 'p')->fields('p')->condition('recipe_version_id', $recipeVersionId)->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function lines(int $recipeVersionId): array {
    return $this->database->select('brebo_calculation_recipe_line', 'l')->fields('l')->condition('recipe_version_id', $recipeVersionId)->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function takeoff(int $takeoffId): ?array {
    $row = $this->database->select('brebo_calculation_takeoff', 't')->fields('t')->condition('id', $takeoffId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function instance(int $instanceId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')->condition('id', $instanceId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function instanceLine(int $lineId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l')->condition('id', $lineId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function instanceParameterValues(int $instanceId): array {
    $values = [];
    foreach ($this->database->select('brebo_calculation_recipe_instance_parameter', 'p')->fields('p')->condition('recipe_instance_id', $instanceId)->execute() as $parameter) {
      $values[(string) $parameter->parameter_key] = (string) $parameter->value;
    }
    return $values;
  }

  public function formulaLines(int $instanceId): array {
    $rows = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l', ['id', 'quantity_formula'])
      ->condition('recipe_instance_id', $instanceId)
      ->condition('is_custom', 0)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => ['id' => (int) $row['id'], 'quantity_formula' => (string) $row['quantity_formula']], $rows);
  }

  public function nextInstanceSortOrder(int $calculationId, string $version, string $paragraphKey): int {
    $query = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->condition('paragraph_key', $paragraphKey);
    $query->addExpression('COALESCE(MAX(sort_order), 0) + 10', 'next_order');
    return (int) $query->execute()->fetchField();
  }

  public function nextLineSortOrder(int $instanceId): int {
    $query = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->condition('recipe_instance_id', $instanceId);
    $query->addExpression('COALESCE(MAX(sort_order), 0) + 10', 'next_order');
    return (int) $query->execute()->fetchField();
  }

  public function insertInstance(array $values): int {
    return (int) $this->database->insert('brebo_calculation_recipe_instance')->fields($values)->execute();
  }

  public function insertInstanceParameter(array $values): void {
    $this->database->insert('brebo_calculation_recipe_instance_parameter')->fields($values)->execute();
  }

  public function insertInstanceLine(array $values): int {
    return (int) $this->database->insert('brebo_calculation_recipe_instance_line')->fields($values)->execute();
  }

  public function updateInstance(int $instanceId, array $values): void {
    $this->database->update('brebo_calculation_recipe_instance')->fields($values)->condition('id', $instanceId)->execute();
  }

  public function updateInstanceParameter(int $instanceId, string $key, array $values): void {
    $this->database->update('brebo_calculation_recipe_instance_parameter')->fields($values)->condition('recipe_instance_id', $instanceId)->condition('parameter_key', $key)->execute();
  }

  public function updateInstanceLine(int $lineId, array $values): void {
    $this->database->update('brebo_calculation_recipe_instance_line')->fields($values)->condition('id', $lineId)->execute();
  }

  public function isEditableVersion(int $calculationId, string $version): bool {
    $row = $this->database->select('brebo_calculation_version', 'v')->fields('v', ['status', 'locked_at'])->condition('calculation_id', $calculationId)->condition('version', $version)->execute()->fetchAssoc();
    return (bool) ($row && $row['status'] === 'draft' && $row['locked_at'] === NULL);
  }

}
