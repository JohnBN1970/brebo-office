<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Synchronizes approved labor hours and calculates labor-cost forecasts.
 */
final class WorkforceLaborCostService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array<string, float|string>
   */
  public function synchronizeAndAnalyze(NodeInterface $budgetLine): array {
    if ($budgetLine->bundle() !== 'brebo_work_budget_line') {
      throw new \InvalidArgumentException('Expected a work budget line.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $approvedHours = 0.0;
    $timeIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_time_entry')
      ->condition('field_brebo_time_budget', $budgetLine->id())
      ->condition('field_brebo_time_status', 'Goedgekeurd')
      ->execute();
    foreach ($storage->loadMultiple($timeIds) as $entry) {
      if ($entry instanceof NodeInterface) {
        $approvedHours += max(0.0, (float) ($entry->get('field_brebo_time_hours')->value ?? 0));
      }
    }

    $currentActual = (float) ($budgetLine->get('field_brebo_actual_hours')->value ?? 0);
    if (abs($currentActual - $approvedHours) > 0.0001) {
      $budgetLine->set('field_brebo_actual_hours', round($approvedHours, 4));
      $budgetLine->setNewRevision(TRUE);
      $budgetLine->setRevisionLogMessage('Werkelijke uren gesynchroniseerd vanuit goedgekeurde BREBO Inzet-urenregistraties.');
      $budgetLine->save();
    }

    $budgetHours = max(0.0, (float) ($budgetLine->get('field_brebo_budget_hours')->value ?? 0));
    $calcLine = $budgetLine->get('field_brebo_calc_line_ref')->entity;
    $laborRate = $calcLine instanceof NodeInterface && $calcLine->hasField('field_brebo_labor_rate')
      ? max(0.0, (float) ($calcLine->get('field_brebo_labor_rate')->value ?? 0))
      : 0.0;

    $remainingScheduledHours = 0.0;
    $shiftIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_shift')
      ->condition('field_brebo_shift_budget', $budgetLine->id())
      ->condition('field_brebo_shift_status', 'Vervallen', '<>')
      ->condition('field_brebo_shift_end', gmdate('Y-m-d\TH:i:s'), '>')
      ->execute();
    foreach ($storage->loadMultiple($shiftIds) as $shift) {
      if (!$shift instanceof NodeInterface) {
        continue;
      }
      $start = strtotime((string) $shift->get('field_brebo_shift_start')->value . ' UTC');
      $end = strtotime((string) $shift->get('field_brebo_shift_end')->value . ' UTC');
      if ($start !== FALSE && $end !== FALSE && $end > $start) {
        $remainingScheduledHours += ($end - $start) / 3600;
      }
    }

    $forecastHours = $approvedHours + $remainingScheduledHours;
    $budgetCost = $budgetHours * $laborRate;
    $actualCost = $approvedHours * $laborRate;
    $forecastCost = $forecastHours * $laborRate;
    $variance = $forecastCost - $budgetCost;

    $status = 'Akkoord';
    if ($budgetHours <= 0 && $forecastHours > 0) {
      $status = 'Blokkade';
    }
    elseif ($forecastHours > $budgetHours + 0.001) {
      $status = 'Overschrijding verwacht';
    }
    elseif ($budgetHours > 0 && $forecastHours >= $budgetHours * 0.9) {
      $status = 'Aandacht';
    }

    return [
      'budget_hours' => round($budgetHours, 2),
      'actual_hours' => round($approvedHours, 2),
      'remaining_scheduled_hours' => round($remainingScheduledHours, 2),
      'forecast_hours' => round($forecastHours, 2),
      'labor_rate' => round($laborRate, 2),
      'budget_cost' => round($budgetCost, 2),
      'actual_cost' => round($actualCost, 2),
      'forecast_cost' => round($forecastCost, 2),
      'variance' => round($variance, 2),
      'status' => $status,
    ];
  }

}
