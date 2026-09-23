<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\user\UserInterface;

/**
 * Resolves the single locked labour line that matches an employee hourly cost.
 */
final class PersonnelLabourLineResolver {

  public function __construct(
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  /**
   * @return array{id:int,hourly_cost:float,description:string}
   */
  public function resolve(int $projectId, UserInterface $account): array {
    if (!$account->hasField('field_brebo_hourly_cost')) {
      throw new \UnexpectedValueException(sprintf('Medewerker %s heeft nog geen kostprijsveld.', $account->getDisplayName()));
    }

    $hourlyCost = round((float) ($account->get('field_brebo_hourly_cost')->value ?? 0), 2);
    if ($hourlyCost <= 0) {
      throw new \UnexpectedValueException(sprintf('Vul eerst de interne kostprijs per uur in bij %s.', $account->getDisplayName()));
    }

    $matches = [];
    foreach ($this->labourProductivity->labourBudgetLines($projectId) as $line) {
      $lineCost = round((float) ($line['hourly_cost_ex_vat'] ?? 0), 2);
      if (abs($lineCost - $hourlyCost) < 0.001) {
        $matches[] = $line;
      }
    }

    if ($matches === []) {
      throw new \UnexpectedValueException(sprintf(
        'Geen vergrendelde arbeidsregel met kostprijs € %.2f/u gevonden voor %s.',
        $hourlyCost,
        $account->getDisplayName(),
      ));
    }
    if (count($matches) > 1) {
      throw new \UnexpectedValueException(sprintf(
        'Meerdere arbeidsregels met kostprijs € %.2f/u gevonden. Bundel arbeid in de werkbegroting tot één regel per uurtarief.',
        $hourlyCost,
      ));
    }

    return [
      'id' => (int) $matches[0]['id'],
      'hourly_cost' => $hourlyCost,
      'description' => (string) ($matches[0]['description'] ?? 'Arbeid'),
    ];
  }

}
