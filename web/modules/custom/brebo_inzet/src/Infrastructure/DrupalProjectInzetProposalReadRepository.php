<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\ProjectInzetProposalReadRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal entity adapter for project inzet proposal source reads. */
final class DrupalProjectInzetProposalReadRepository implements ProjectInzetProposalReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function source(int $projectId): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $packageIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', $projectId)
      ->execute();

    $detectedStart = NULL;
    $detectedEnd = NULL;
    foreach ($storage->loadMultiple($packageIds) as $package) {
      if (!$package instanceof NodeInterface) {
        continue;
      }
      $packageStart = trim((string) ($package->get('field_brebo_planned_start')->value ?? ''));
      $packageEnd = trim((string) ($package->get('field_brebo_planned_end')->value ?? ''));
      if ($packageStart !== '' && ($detectedStart === NULL || $packageStart < $detectedStart)) {
        $detectedStart = $packageStart;
      }
      if ($packageEnd !== '' && ($detectedEnd === NULL || $packageEnd > $detectedEnd)) {
        $detectedEnd = $packageEnd;
      }
    }

    $budgetHours = 0.0;
    if ($packageIds !== []) {
      $candidateBudgetIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget')
        ->condition('field_brebo_package_ref', array_values($packageIds), 'IN')
        ->sort('changed', 'DESC')
        ->execute();

      $budgetIds = [];
      foreach ($storage->loadMultiple($candidateBudgetIds) as $budget) {
        if (!$budget instanceof NodeInterface) {
          continue;
        }
        $packageId = (int) ($budget->get('field_brebo_package_ref')->target_id ?? 0);
        if ($packageId > 0 && !isset($budgetIds[$packageId])) {
          $budgetIds[$packageId] = (int) $budget->id();
        }
      }

      if ($budgetIds !== []) {
        $lineIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'brebo_work_budget_line')
          ->condition('field_brebo_work_budget_ref', array_values($budgetIds), 'IN')
          ->execute();
        foreach ($storage->loadMultiple($lineIds) as $line) {
          if ($line instanceof NodeInterface) {
            $budgetHours += max(0.0, (float) ($line->get('field_brebo_budget_hours')->value ?? 0));
          }
        }
      }
    }

    return [
      'start' => $detectedStart,
      'end' => $detectedEnd,
      'budget_hours' => $budgetHours,
    ];
  }

}
