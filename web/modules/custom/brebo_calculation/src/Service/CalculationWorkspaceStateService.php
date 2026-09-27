<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds one framework-neutral read model for the calculation workspace.
 */
final class CalculationWorkspaceStateService {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationContextService $contextService,
    private readonly CalculationResultService $resultService,
    private readonly CalculationReadinessInspector $readinessInspector,
  ) {}

  /** @return array<string,mixed> */
  public function state(int $calculationId): array {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$version) {
      throw new \RuntimeException('Calculation version not found.');
    }

    $versionName = (string) $version['version'];
    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $versionName)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $versionName)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('row_id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $recipes = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $versionName)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $subcalculations = $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $versionName)
      ->orderBy('label')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return [
      'contract' => 'brebo-calculation-workspace-v2',
      'calculation' => $this->contextService->get($calculationId) ?? [
        'calculation_id' => $calculationId,
        'code' => NULL,
        'label' => 'Calculatie ' . $calculationId,
        'package_id' => NULL,
        'project_id' => NULL,
        'project_label' => NULL,
      ],
      'version' => $version,
      'editable' => (string) $version['status'] === 'draft' && $version['locked_at'] === NULL,
      'structure' => $structure,
      'rows' => $rows,
      'recipes' => $recipes,
      'subcalculations' => $subcalculations,
      'result' => $this->resultService->calculate($calculationId, $versionName),
      'readiness' => $this->readinessInspector->inspect($calculationId, $versionName),
    ];
  }

}
