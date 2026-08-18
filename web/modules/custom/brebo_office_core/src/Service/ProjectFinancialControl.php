<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a live project forecast from approved work budgets and labor actuals.
 */
final class ProjectFinancialControl {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceLaborCostService $laborCost,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function analyze(NodeInterface $project): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Expected a BREBO project.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $packageIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', $project->id())
      ->execute();

    $budgetIds = [];
    if ($packageIds) {
      $budgetIds = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget')
        ->condition('field_brebo_package_ref', array_values($packageIds), 'IN')
        ->execute();
    }

    $budgetCost = 0.0;
    $actualLaborCost = 0.0;
    $forecastLaborCost = 0.0;
    $budgetHours = 0.0;
    $actualHours = 0.0;
    $forecastHours = 0.0;
    $lineCount = 0;
    $signals = [];
    $rows = [];

    if ($budgetIds) {
      $lineIds = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget_line')
        ->condition('field_brebo_work_budget_ref', array_values($budgetIds), 'IN')
        ->execute();
      foreach ($storage->loadMultiple($lineIds) as $line) {
        if (!$line instanceof NodeInterface) {
          continue;
        }
        $lineCount++;
        $source = $line->get('field_brebo_calc_line_ref')->entity;
        $directCost = $source instanceof NodeInterface && $source->hasField('field_brebo_direct_cost')
          ? max(0.0, (float) ($source->get('field_brebo_direct_cost')->value ?? 0)) : 0.0;
        $finance = $this->laborCost->synchronizeAndAnalyze($line);
        $budgetCost += $directCost;
        $actualLaborCost += (float) $finance['actual_cost'];
        $forecastLaborCost += (float) $finance['forecast_cost'];
        $budgetHours += (float) $finance['budget_hours'];
        $actualHours += (float) $finance['actual_hours'];
        $forecastHours += (float) $finance['forecast_hours'];
        if ($finance['status'] !== 'Akkoord') {
          $signals[] = $line->label() . ': ' . $finance['status'];
        }
        $rows[] = [
          'label' => $line->label(),
          'budget_hours' => $finance['budget_hours'],
          'actual_hours' => $finance['actual_hours'],
          'forecast_hours' => $finance['forecast_hours'],
          'labor_rate' => $finance['labor_rate'],
          'actual_labor_cost' => $finance['actual_cost'],
          'forecast_labor_cost' => $finance['forecast_cost'],
          'status' => $finance['status'],
        ];
      }
    }

    // Only labor actuals are currently backed by approved operational data.
    // Non-labor actuals/commitments stay explicitly unknown until the finance
    // objects are linked, rather than being guessed from budget data.
    $budgetLaborCost = 0.0;
    foreach ($rows as $row) {
      $budgetLaborCost += (float) $row['budget_hours'] * (float) $row['labor_rate'];
    }
    $nonLaborBudget = max(0.0, $budgetCost - $budgetLaborCost);
    $forecastCost = $nonLaborBudget + $forecastLaborCost;
    $variance = $forecastCost - $budgetCost;
    $status = 'Akkoord';
    if ($variance > 0.01) {
      $status = 'Overschrijding verwacht';
    }
    elseif ($budgetCost > 0 && $forecastCost >= $budgetCost * 0.95) {
      $status = 'Aandacht';
    }
    if (!$budgetIds || $lineCount === 0) {
      $status = 'Onvoldoende data';
      $signals[] = 'Geen werkbegrotingsregels beschikbaar voor financiële projectsturing.';
    }

    return [
      'status' => $status,
      'work_budgets' => count($budgetIds),
      'lines' => $lineCount,
      'budget_cost' => round($budgetCost, 2),
      'budget_labor_cost' => round($budgetLaborCost, 2),
      'non_labor_budget' => round($nonLaborBudget, 2),
      'actual_labor_cost' => round($actualLaborCost, 2),
      'forecast_labor_cost' => round($forecastLaborCost, 2),
      'forecast_cost' => round($forecastCost, 2),
      'variance' => round($variance, 2),
      'budget_hours' => round($budgetHours, 2),
      'actual_hours' => round($actualHours, 2),
      'forecast_hours' => round($forecastHours, 2),
      'signals' => array_values(array_unique($signals)),
      'rows' => $rows,
      'actual_scope' => 'Arbeid op basis van goedgekeurde uren; overige werkelijke kosten en verplichtingen nog niet gekoppeld.',
    ];
  }

}
