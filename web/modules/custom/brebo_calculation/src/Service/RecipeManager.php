<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\RecipeRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Manages reusable recipes and version-pinned calculation recipe instances. */
final class RecipeManager {

  public function __construct(
    private readonly RecipeRepositoryInterface $repository,
    private readonly RecipeFormulaEvaluator $formulaEvaluator,
    private readonly CalculationAccessGatewayInterface $accessGateway,
  ) {}

  /**
   * Places a published recipe version into an editable calculation version.
   *
   * Available variables in recipe formulas:
   * top_m, bottom_m, left_m, right_m, perimeter_m, area_m2,
   * width_mm, height_mm, quantity, element_quantity and passes.
   *
   * Geometry variables are per element. Use quantity/element_quantity in the
   * formula when the result must cover all elements.
   *
   * @param array<string,int|float|string> $parameterValues
   */
  public function placeRecipe(int $calculationId, string $calculationVersion, string $paragraphKey, int $recipeVersionId, float $quantity, array $parameterValues, int $actorId, array $contextVariables = []): int {
    $this->assertEditableCalculation($calculationId, $calculationVersion, $actorId);
    if ($quantity < 0) { throw new \InvalidArgumentException('Recipe quantity cannot be negative.'); }
    $recipeVersion = $this->repository->publishedVersion($recipeVersionId);
    if (!$recipeVersion) { throw new \InvalidArgumentException('Published recipe version not found.'); }
    $recipe = $this->repository->recipe((int) $recipeVersion['recipe_id']);
    if (!$recipe) { throw new \RuntimeException('Recipe identity not found.'); }
    $parameters = $this->repository->parameters($recipeVersionId);
    $resolved = $this->resolveParameters($parameters, $parameterValues, $quantity, $contextVariables);
    $lines = $this->repository->lines($recipeVersionId);
    $snapshot = ['recipe' => ['id' => (int) $recipe['id'], 'key' => (string) $recipe['recipe_key'], 'name' => (string) $recipe['name']], 'version' => ['id' => $recipeVersionId, 'version' => (string) $recipeVersion['version']], 'parameters' => $parameters, 'lines' => $lines, 'context_variables' => $contextVariables];
    $payload = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', $payload);
    $sortOrder = $this->repository->nextInstanceSortOrder($calculationId, $calculationVersion, $paragraphKey);
      $instanceId = $this->repository->insertInstance(['calculation_id' => $calculationId, 'calculation_version' => $calculationVersion, 'paragraph_key' => $paragraphKey, 'recipe_id' => (int) $recipe['id'], 'recipe_version_id' => $recipeVersionId, 'name' => (string) $recipe['name'], 'quantity' => $quantity, 'unit' => (string) $recipeVersion['base_unit'], 'sort_order' => $sortOrder, 'snapshot_payload' => $payload, 'snapshot_hash' => $hash, 'created' => time(), 'created_by' => $actorId]);
      foreach ($resolved as $key => $value) { $this->repository->insertInstanceParameter(['recipe_instance_id' => $instanceId, 'parameter_key' => $key, 'value' => (string) ($parameterValues[$key] ?? ''), 'calculated_value' => (string) $value]); }
      $variables = $contextVariables + $resolved + ['quantity' => $quantity];
      foreach ($lines as $line) {
        $calculatedQuantity = $this->formulaEvaluator->evaluate((string) ($line['quantity_formula'] ?? ''), $variables);
        $this->repository->insertInstanceLine(['recipe_instance_id' => $instanceId, 'source_recipe_line_id' => (int) $line['id'], 'line_key' => (string) $line['line_key'], 'line_type' => (string) $line['line_type'], 'description' => (string) $line['description'], 'unit' => $line['unit'], 'quantity_formula' => $line['quantity_formula'], 'calculated_quantity' => $calculatedQuantity, 'manual_quantity' => NULL, 'waste_pct' => $line['waste_pct'], 'material_ref' => $line['material_ref'], 'price_source_ref' => $line['price_source_ref'], 'unit_cost' => $line['unit_cost'], 'sort_order' => (int) $line['sort_order'], 'is_custom' => 0]);
      }
    return $instanceId;
  }

  public function updateQuantity(int $instanceId, float $quantity, int $actorId): void {
    if ($quantity < 0) { throw new \InvalidArgumentException('Recipe quantity cannot be negative.'); }
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $this->repository->updateInstance($instanceId, ['quantity' => $quantity]);
    $this->recalculate($instanceId, $actorId);
  }

  /** @param array<string,int|float|string> $values */
  public function updateParameters(int $instanceId, array $values, int $actorId): void {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $snapshot = json_decode((string) $instance['snapshot_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $definitions = is_array($snapshot['parameters'] ?? NULL) ? $snapshot['parameters'] : [];
    $allowed = [];
    foreach ($definitions as $definition) { $allowed[(string) $definition['parameter_key']] = $definition; }
    foreach ($values as $key => $value) {
      if (!isset($allowed[$key])) { throw new \InvalidArgumentException('Unknown recipe parameter: ' . $key); }
      if ($value !== '' && !is_numeric($value)) { throw new \InvalidArgumentException('Recipe parameter must be numeric: ' . $key); }
      $this->repository->updateInstanceParameter($instanceId, $key, ['value' => (string) $value]);
    }
    $this->recalculate($instanceId, $actorId);
  }

  /** Changes a line override without touching its formula-driven base quantity. */
  public function updateLineOverride(int $lineId, ?float $manualQuantity, float $wastePct, int $actorId): void {
    if ($manualQuantity !== NULL && $manualQuantity < 0) { throw new \InvalidArgumentException('Manual recipe line quantity cannot be negative.'); }
    if ($wastePct < 0 || $wastePct > 1000) { throw new \InvalidArgumentException('Recipe line waste percentage is outside the allowed range.'); }
    $line = $this->repository->instanceLine($lineId);
    if (!$line) { throw new \InvalidArgumentException('Recipe instance line not found.'); }
    $instance = $this->loadInstance((int) $line['recipe_instance_id']);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $this->repository->updateInstanceLine($lineId, ['manual_quantity' => $manualQuantity, 'waste_pct' => $wastePct]);
  }

  /** Removes the manual quantity override so the calculated value becomes active again. */
  public function resetLineQuantityOverride(int $lineId, int $actorId): void {
    $line = $this->repository->instanceLine($lineId);
    if (!$line) { throw new \InvalidArgumentException('Recipe instance line not found.'); }
    $instance = $this->loadInstance((int) $line['recipe_instance_id']);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $this->repository->updateInstanceLine($lineId, ['manual_quantity' => NULL]);
  }

  public function recalculate(int $instanceId, int $actorId): void {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $quantity = (float) $instance['quantity'];
    $snapshot = json_decode((string) $instance['snapshot_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $parameters = is_array($snapshot['parameters'] ?? NULL) ? $snapshot['parameters'] : [];
    $stored = $this->repository->instanceParameterValues($instanceId);
    $contextVariables = is_array($snapshot['context_variables'] ?? NULL) ? $snapshot['context_variables'] : [];
    // element_quantity is an explicit alias of the current recipe quantity.
    // Keep it synchronized when users edit the placed instance quantity.
    $contextVariables['element_quantity'] = $quantity;
    $resolved = $this->resolveParameters($parameters, $stored, $quantity, $contextVariables);
    foreach ($resolved as $key => $value) { $this->repository->updateInstanceParameter($instanceId, $key, ['calculated_value' => (string) $value]); }
    $variables = $contextVariables + $resolved + ['quantity' => $quantity];
    foreach ($this->repository->formulaLines($instanceId) as $line) {
      $calculatedQuantity = $this->formulaEvaluator->evaluate((string) $line['quantity_formula'], $variables);
      $this->repository->updateInstanceLine((int) $line['id'], ['calculated_quantity' => $calculatedQuantity]);
    }
  }

  /** @param array<string,mixed> $line */
  public function addCustomLine(int $instanceId, array $line, int $actorId): int {
    $instance = $this->loadInstance($instanceId);
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);
    $sortOrder = $this->repository->nextLineSortOrder($instanceId);
    return (int) $this->repository->insertInstanceLine(['recipe_instance_id' => $instanceId, 'source_recipe_line_id' => NULL, 'line_key' => 'custom-' . bin2hex(random_bytes(8)), 'line_type' => (string) ($line['line_type'] ?? 'material'), 'description' => trim((string) ($line['description'] ?? 'Nieuwe regel')), 'unit' => $line['unit'] ?? NULL, 'quantity_formula' => $line['quantity_formula'] ?? NULL, 'calculated_quantity' => (float) ($line['quantity'] ?? 0), 'manual_quantity' => isset($line['quantity']) ? (float) $line['quantity'] : NULL, 'waste_pct' => (float) ($line['waste_pct'] ?? 0), 'material_ref' => $line['material_ref'] ?? NULL, 'price_source_ref' => $line['price_source_ref'] ?? NULL, 'unit_cost' => $line['unit_cost'] ?? NULL, 'sort_order' => $sortOrder, 'is_custom' => 1]);
  }

  /**
   * Places a published recipe using one geometry take-off row as formula context.
   *
   * @param array<string,int|float|string> $parameterValues
   */
  public function placeRecipeFromTakeoff(
    int $calculationId,
    string $calculationVersion,
    string $paragraphKey,
    int $recipeVersionId,
    int $takeoffId,
    float $passes,
    array $parameterValues,
    int $actorId,
  ): int {
    if ($passes < 0) {
      throw new \InvalidArgumentException('Recipe passes cannot be negative.');
    }
    $takeoff = $this->repository->takeoff($takeoffId);
    if (!$takeoff) {
      throw new \InvalidArgumentException('Calculation take-off row not found.');
    }
    $context = [
      'takeoff_id' => $takeoffId,
      'top_m' => (float) ($takeoff['top_m'] ?? 0),
      'bottom_m' => (float) ($takeoff['bottom_m'] ?? 0),
      'left_m' => (float) ($takeoff['left_m'] ?? 0),
      'right_m' => (float) ($takeoff['right_m'] ?? 0),
      'perimeter_m' => (float) ($takeoff['perimeter_m'] ?? 0),
      'area_m2' => (float) ($takeoff['area_m2'] ?? 0),
      'width_mm' => (float) ($takeoff['width_mm'] ?? 0),
      'height_mm' => (float) ($takeoff['height_mm'] ?? 0),
      'passes' => $passes,
      'element_quantity' => (float) ($takeoff['quantity'] ?? 1),
    ];
    return $this->placeRecipe(
      $calculationId,
      $calculationVersion,
      $paragraphKey,
      $recipeVersionId,
      (float) ($takeoff['quantity'] ?? 1),
      $parameterValues,
      $actorId,
      $context,
    );
  }

  /** @return array<string,mixed> */
  private function loadInstance(int $instanceId): array {
    $instance = $this->repository->instance($instanceId);
    if (!$instance) { throw new \InvalidArgumentException('Recipe instance not found.'); }
    return $instance;
  }


  /** @param list<array<string,mixed>> $parameters @param array<string,int|float|string> $values @return array<string,float> */
  private function resolveParameters(array $parameters, array $values, float $quantity, array $contextVariables = []): array {
    $resolved = [];
    foreach ($parameters as $parameter) {
      $key = (string) $parameter['parameter_key']; $raw = $values[$key] ?? $parameter['default_value'] ?? NULL;
      if ($raw !== NULL && $raw !== '' && is_numeric($raw)) { $resolved[$key] = (float) $raw; continue; }
      $formula = trim((string) ($parameter['formula'] ?? ''));
      if ($formula !== '') { $resolved[$key] = $this->formulaEvaluator->evaluate($formula, $contextVariables + $resolved + ['quantity' => $quantity]); continue; }
      if ((int) $parameter['required'] === 1) { throw new \InvalidArgumentException('Required recipe parameter missing: ' . $key); }
      $resolved[$key] = 0.0;
    }
    return $resolved;
  }

  private function assertEditableCalculation(int $calculationId, string $version, ?int $actorId = NULL): void {
    if ($actorId !== NULL) {
      $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    }
    if (!$this->repository->isEditableVersion($calculationId, $version)) { throw new \RuntimeException('Only unlocked draft calculation versions may be changed.'); }
  }
}
