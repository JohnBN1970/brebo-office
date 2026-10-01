<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationWorkspaceReadRepositoryInterface;

/**
 * Builds one framework-neutral read model for the calculation workspace.
 */
final class CalculationWorkspaceStateService {

  public function __construct(
    private readonly CalculationWorkspaceReadRepositoryInterface $repository,
    private readonly CalculationContextService $contextService,
    private readonly CalculationResultService $resultService,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly CalcResultSnapshotService $calcResultSnapshot,
  ) {}

  /** @return array<string,mixed> */
  public function state(int $calculationId): array {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    $version = $this->repository->latestVersion($calculationId);
    if (!$version) {
      throw new \RuntimeException('Calculation version not found.');
    }

    $versionName = (string) $version['version'];
    $structure = $this->repository->structure($calculationId, $versionName);
    $rows = $this->repository->rows($calculationId, $versionName);
    $recipes = $this->repository->recipes($calculationId, $versionName);
    $subcalculations = $this->repository->subcalculations($calculationId, $versionName);

    $calcResult = $this->calcResultSnapshot->latestReadModel($calculationId);
    if ($calcResult !== NULL) {
      $calcResult['current_for_office_version'] = (string) ($calcResult['office_version'] ?? '') === $versionName;
    }

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
      'calc_result' => $calcResult,
      'readiness' => $this->readinessInspector->inspect($calculationId, $versionName),
    ];
  }

}
