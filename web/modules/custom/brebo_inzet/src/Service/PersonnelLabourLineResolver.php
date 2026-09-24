<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\user\UserInterface;

/**
 * Allocates personnel planning to labour budget capacity, independent of rate.
 */
final class PersonnelLabourLineResolver {

  public function __construct(
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  /**
   * Returns the labour budget line with the most remaining planned capacity.
   *
   * Employee cost is deliberately NOT used to choose the budget line. The
   * working-budget rate is a baseline assumption; the employee rate is an
   * actual/forecast cost characteristic.
   *
   * @return array{id:int,employee_hourly_cost:float,description:string}
   */
  public function resolve(int $projectId, UserInterface $account): array {
    $lines = $this->labourProductivity->labourBudgetLines($projectId);
    if ($lines === []) {
      throw new \UnexpectedValueException('Geen vergrendelde arbeidsbegroting met arbeid gevonden voor dit project.');
    }

    $analysis = $this->labourProductivity->analyzeProject($projectId);
    $plannedByLine = [];
    foreach ((array) ($analysis['lines'] ?? []) as $row) {
      $plannedByLine[(int) ($row['budget_line_id'] ?? 0)] = (float) ($row['planned_hours'] ?? 0);
    }

    usort($lines, static function (array $a, array $b) use ($plannedByLine): int {
      $aRemaining = (float) ($a['budget_hours'] ?? 0) - ($plannedByLine[(int) ($a['id'] ?? 0)] ?? 0.0);
      $bRemaining = (float) ($b['budget_hours'] ?? 0) - ($plannedByLine[(int) ($b['id'] ?? 0)] ?? 0.0);
      if (abs($aRemaining - $bRemaining) < 0.0001) {
        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
      }
      return $aRemaining < $bRemaining ? 1 : -1;
    });

    $employeeHourlyCost = 0.0;
    if ($account->hasField('field_brebo_hourly_cost')) {
      $employeeHourlyCost = round((float) ($account->get('field_brebo_hourly_cost')->value ?? 0), 2);
    }

    return [
      'id' => (int) $lines[0]['id'],
      'employee_hourly_cost' => max(0.0, $employeeHourlyCost),
      'description' => (string) ($lines[0]['description'] ?? 'Arbeid'),
    ];
  }

}
