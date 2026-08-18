<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Manages reusable recipes and version-pinned calculation recipe instances. */
final class RecipeManager {

  public function __construct(
    private readonly Connection $database,
    private readonly RecipeFormulaEvaluator $formulaEvaluator,
  ) {}

  /**
   * Places a published recipe version into an editable calculation version.
   *
   * @param array<string,int|float|string> $parameterValues
   */
  public function placeRecipe(int $calculationId, string $calculationVersion, string $paragraphKey, int $recipeVersionId, float $quantity, array $parameterValues, AccountInterface $actor): int {
    $this->assertEditableCalculation($calculationId, $calculationVersion);
    if ($quantity < 0) { throw new \InvalidArgumentException('Recipe quantity cannot be negative.'); }
    $recipeVersion = $this->database->select('brebo_calculation_recipe_version', 'rv')->fields('rv')->condition('id', $recipeVersionId)->condition('status', 'published')->execute()->fetchAssoc();
    if (!$recipeVersion) { throw new \InvalidArgumentException('Published recipe version not found.'); }
    $recipe = $this->database->select('brebo_calculation_recipe', 'r')->fields('r')->condition('id', (int) $recipeVersion['recipe_id'])->execute()->fetchAssoc();
    if (!$recipe) { throw new \RuntimeException('Recipe identity not found.'); }
    $parameters = $this->loadParameters($recipeVersionId);
    $resolved = $this->resolveParameters($parameters, $parameterValues, $quantity);
    $lines = $this->loadLines($recipeVersionId);
    $snapshot = ['recipe' => ['id' => (int) $recipe['id'], 'key' => (string) $recipe['recipe_key'], 'name' => (string) $recipe['name']], 'version' => ['id' => $recipeVersionId, 'version' => (string) $recipeVersion['version']], 'parameters' => $parameters, 'lines' => $lines];
    $payload = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', $payload);
    $transaction = $this->database->startTransaction();
    try {
      $sortOrder = (int) $this->database->select('brebo_calculation_recipe_instance', 'i')->condition('calculation_id', $calculationId)->condition('calculation_version', $calculationVersion)->condition('paragraph_key', $paragraphKey)->addExpression('COALESCE(MAX(sort_order), 0) + 10', 'next_order')->execute()->fetchField();
      $instanceId = (int) $this->database->insert('brebo_calculation_recipe_instance')->fields(['calculation_id' => $calculationId, 'calculation_version' => $calculationVersion, 'paragraph_key' => $paragraphKey, 'recipe_id' => (int) $recipe['id'], 'recipe_version_id' => $recipeVersionId, 'name' => (string) $recipe['name'], 'quantity' => $quantity, 'unit' => (string) $recipeVersion['base_unit'], 'sort_order' => $sortOrder, 'snapshot_payload' => $payload, 'snapshot_hash' => $hash, 'created' => time(), 'created_by' => (int) $actor->id()])->execute();
      foreach ($resolved as $key => $value) { $this->database->insert('brebo_calculation_recipe_instance_parameter')->fields(['recipe_instance_id' => $instanceId, 'parameter_key' => $key, 'value' => (string) ($parameterValues[$key] ?? ''), 'calculated_value' => (string) $value])->execute(); }
      $variables = $resolved + ['quantity' => $quantity];
      foreach ($lines as $line) {
        $calculatedQuantity = $this->formulaEvaluator->evaluate((string) ($line['quantity_formula'] ?? ''), $variables);
        $this->database->insert('brebo_calculation_recipe_instance_line')->fields(['recipe_instance_id' => $instanceId, 'source_recipe_line_id' => (int) $line['id'], 'line_key' => (string) $line['line_key'], 'line_type' => (string) $line['line_type'], 'description' => (string) $line['description'], 'unit' => $line['unit'], 'quantity_formula' => $line['quantity_formula'], 'calculated_quantity' => $calculatedQuantity, 'manual_quantity' => NULL, 'waste_pct' => $line['waste_pct'], 'material_ref' => $line['material_ref'], 'price_source_ref' => $line['price_source_ref'], 'unit_cost' => $line['unit_cost'], 'sort_order' => (int) $line['sort_order'], 'is_custom' => 0])->execute();
      }
      return $instanceId;
    }
    catch (\Throwable $exception) { $transaction->rollBack(); throw $exception; }
  }

  public function updateQuantity(int $instanceId, float $quantity, AccountInterface $actor): void {
    if ($quantity < 0) { throw new \InvalidArgumentException('Recipe quantity cannot be negative.'); }
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version']);
    $this->database->update('brebo_calculation_recipe_instance')->fields(['quantity' => $quantity])->condition('id', $instanceId)->execute();
    $this->recalculate($instanceId, $actor);
  }

  /** @param array<string,int|float|string> $values */
  public function updateParameters(int $instanceId, array $values, AccountInterface $actor): void {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version']);
    $snapshot = json_decode((string) $instance['snapshot_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $definitions = is_array($snapshot['parameters'] ?? NULL) ? $snapshot['parameters'] : [];
    $allowed = [];
    foreach ($definitions as $definition) { $allowed[(string) $definition['parameter_key']] = $definition; }
    foreach ($values as $key => $value) {
      if (!isset($allowed[$key])) { throw new \InvalidArgumentException('Unknown recipe parameter: ' . $key); }
      if ($value !== '' && !is_numeric($value)) { throw new \InvalidArgumentException('Recipe parameter must be numeric: ' . $key); }
      $this->database->update('brebo_calculation_recipe_instance_parameter')->fields(['value' => (string) $value])->condition('recipe_instance_id', $instanceId)->condition('parameter_key', $key)->execute();
    }
    $this->recalculate($instanceId, $actor);
  }

  public function recalculate(int $instanceId, AccountInterface $actor): void {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version']);
    $quantity = (float) $instance['quantity'];
    $snapshot = json_decode((string) $instance['snapshot_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $parameters = is_array($snapshot['parameters'] ?? NULL) ? $snapshot['parameters'] : [];
    $stored = [];
    $result = $this->database->select('brebo_calculation_recipe_instance_parameter', 'p')->fields('p')->condition('recipe_instance_id', $instanceId)->execute();
    foreach ($result as $parameter) { $stored[(string) $parameter->parameter_key] = (string) $parameter->value; }
    $resolved = $this->resolveParameters($parameters, $stored, $quantity);
    foreach ($resolved as $key => $value) { $this->database->update('brebo_calculation_recipe_instance_parameter')->fields(['calculated_value' => (string) $value])->condition('recipe_instance_id', $instanceId)->condition('parameter_key', $key)->execute(); }
    $variables = $resolved + ['quantity' => $quantity];
    $result = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l', ['id', 'quantity_formula'])->condition('recipe_instance_id', $instanceId)->condition('is_custom', 0)->execute();
    foreach ($result as $line) {
      $calculatedQuantity = $this->formulaEvaluator->evaluate((string) $line->quantity_formula, $variables);
      $this->database->update('brebo_calculation_recipe_instance_line')->fields(['calculated_quantity' => $calculatedQuantity])->condition('id', (int) $line->id)->execute();
    }
  }

  /** @param array<string,mixed> $line */
  public function addCustomLine(int $instanceId, array $line, AccountInterface $actor): int {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version']);
    $sortOrder = (int) $this->database->select('brebo_calculation_recipe_instance_line', 'l')->condition('recipe_instance_id', $instanceId)->addExpression('COALESCE(MAX(sort_order), 0) + 10', 'next_order')->execute()->fetchField();
    return (int) $this->database->insert('brebo_calculation_recipe_instance_line')->fields(['recipe_instance_id' => $instanceId, 'source_recipe_line_id' => NULL, 'line_key' => 'custom-' . bin2hex(random_bytes(8)), 'line_type' => (string) ($line['line_type'] ?? 'material'), 'description' => trim((string) ($line['description'] ?? 'Nieuwe regel')), 'unit' => $line['unit'] ?? NULL, 'quantity_formula' => $line['quantity_formula'] ?? NULL, 'calculated_quantity' => (float) ($line['quantity'] ?? 0), 'manual_quantity' => isset($line['quantity']) ? (float) $line['quantity'] : NULL, 'waste_pct' => (float) ($line['waste_pct'] ?? 0), 'material_ref' => $line['material_ref'] ?? NULL, 'price_source_ref' => $line['price_source_ref'] ?? NULL, 'unit_cost' => $line['unit_cost'] ?? NULL, 'sort_order' => $sortOrder, 'is_custom' => 1])->execute();
  }

  /** @return array<string,mixed> */
  private function loadInstance(int $instanceId): array {
    $instance = $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')->condition('id', $instanceId)->execute()->fetchAssoc();
    if (!$instance) { throw new \InvalidArgumentException('Recipe instance not found.'); }
    return $instance;
  }

  /** @return list<array<string,mixed>> */
  private function loadParameters(int $recipeVersionId): array { return $this->database->select('brebo_calculation_recipe_parameter', 'p')->fields('p')->condition('recipe_version_id', $recipeVersionId)->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC); }
  /** @return list<array<string,mixed>> */
  private function loadLines(int $recipeVersionId): array { return $this->database->select('brebo_calculation_recipe_line', 'l')->fields('l')->condition('recipe_version_id', $recipeVersionId)->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC); }

  /** @param list<array<string,mixed>> $parameters @param array<string,int|float|string> $values @return array<string,float> */
  private function resolveParameters(array $parameters, array $values, float $quantity): array {
    $resolved = [];
    foreach ($parameters as $parameter) {
      $key = (string) $parameter['parameter_key']; $raw = $values[$key] ?? $parameter['default_value'] ?? NULL;
      if ($raw !== NULL && $raw !== '' && is_numeric($raw)) { $resolved[$key] = (float) $raw; continue; }
      $formula = trim((string) ($parameter['formula'] ?? ''));
      if ($formula !== '') { $resolved[$key] = $this->formulaEvaluator->evaluate($formula, $resolved + ['quantity' => $quantity]); continue; }
      if ((int) $parameter['required'] === 1) { throw new \InvalidArgumentException('Required recipe parameter missing: ' . $key); }
      $resolved[$key] = 0.0;
    }
    return $resolved;
  }

  private function assertEditableCalculation(int $calculationId, string $version): void {
    $record = $this->database->select('brebo_calculation_version', 'v')->fields('v', ['status', 'locked_at'])->condition('calculation_id', $calculationId)->condition('version', $version)->execute()->fetchAssoc();
    if (!$record || $record['status'] !== 'draft' || $record['locked_at'] !== NULL) { throw new \RuntimeException('Only unlocked draft calculation versions may be changed.'); }
  }
}
