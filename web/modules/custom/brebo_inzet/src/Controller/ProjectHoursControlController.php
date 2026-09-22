<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\PersonnelAssignmentComparison;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Project-level planned-versus-actual labour-hour control.
 */
final class ProjectHoursControlController extends ControllerBase {

  public function __construct(
    private readonly PersonnelAssignmentComparison $comparison,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.personnel_assignment_comparison'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Urencontrole · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $storage = $this->entityTypeManager()->getStorage('node');
    $today = (new DrupalDateTime('now'))->format('Y-m-d');

    $assignmentIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_plan_date', 'DESC')
      ->sort('field_brebo_assignment_start', 'ASC')
      ->execute();

    $plannedTotal = 0.0;
    $clockedTotal = 0.0;
    $futurePlanned = 0.0;
    $rows = [];
    $exceptions = 0;

    foreach ($storage->loadMultiple($assignmentIds) as $assignment) {
      if (!$assignment instanceof NodeInterface || !$assignment->access('view')) {
        continue;
      }
      $actual = $this->comparison->compare($assignment);
      $planned = (float) $actual['planned_hours'];
      $clocked = (float) $actual['clocked_hours'];
      $plannedTotal += $planned;
      $clockedTotal += $clocked;

      $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
      if ($date > $today) {
        $futurePlanned += $planned;
      }

      $state = (string) $actual['state'];
      if (in_array($state, ['under', 'over', 'unclocked', 'clocked_without_plan', 'incomplete'], TRUE)) {
        $exceptions++;
      }

      $person = $assignment->get('field_brebo_plan_user')->entity;
      $delta = (float) $actual['delta_hours'];
      $rows[] = [
        $person ? $person->label() : $this->t('Onbekende medewerker'),
        $date,
        trim(
          (string) ($assignment->get('field_brebo_assignment_start')->value ?? '') .
          ' - ' .
          (string) ($assignment->get('field_brebo_assignment_end')->value ?? ''),
          ' -'
        ) ?: '—',
        number_format($planned, 2, ',', '.') . ' u',
        number_format($clocked, 2, ',', '.') . ' u',
        ($delta > 0 ? '+' : '') . number_format($delta, 2, ',', '.') . ' u',
        $this->stateLabel($state),
      ];
    }

    $budgetHours = $this->budgetHours($node);
    $forecast = round($clockedTotal + $futurePlanned, 2);
    $remainingBudget = round($budgetHours - $clockedTotal, 2);
    $forecastDelta = round($forecast - $budgetHours, 2);

    return [
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node:' . $projectId,
          'node_list:brebo_personnel_assignment',
          'node_list:brebo_clock_registration',
          'node_list:brebo_work_budget',
          'node_list:brebo_work_budget_line',
        ],
        'max-age' => 60,
      ],
      'header' => [
        '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1>Urencontrole</h1><p class="brebo-page-header__description">Vergelijk vrijgegeven werkbegrotingsuren met geplande en werkelijk geklokte inzet. De prognose combineert werkelijk geklokte uren tot nu met de nog geplande toekomstige inzet.</p></div>',
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-kpis']],
        'budget' => [
          '#markup' => $this->kpi(number_format($budgetHours, 2, ',', '.') . ' u', 'Werkbegroting', 'neutral'),
        ],
        'planned' => [
          '#markup' => $this->kpi(number_format($plannedTotal, 2, ',', '.') . ' u', 'Totaal gepland', $plannedTotal > $budgetHours && $budgetHours > 0 ? 'critical' : 'neutral'),
        ],
        'clocked' => [
          '#markup' => $this->kpi(number_format($clockedTotal, 2, ',', '.') . ' u', 'Werkelijk geklokt', $clockedTotal > $budgetHours && $budgetHours > 0 ? 'critical' : 'neutral'),
        ],
        'remaining' => [
          '#markup' => $this->kpi(($remainingBudget >= 0 ? '' : '+') . number_format(abs($remainingBudget), 2, ',', '.') . ' u', $remainingBudget >= 0 ? 'Budget resterend' : 'Budget overschreden', $remainingBudget >= 0 ? 'positive' : 'critical'),
        ],
        'forecast' => [
          '#markup' => $this->kpi(number_format($forecast, 2, ',', '.') . ' u', 'Prognose einduren', $forecastDelta > 0 ? 'critical' : 'positive'),
        ],
        'forecast_delta' => [
          '#markup' => $this->kpi(($forecastDelta > 0 ? '+' : '') . number_format($forecastDelta, 2, ',', '.') . ' u', 'Prognose vs begroting', $forecastDelta > 0 ? 'critical' : ($forecastDelta < 0 ? 'attention' : 'positive')),
        ],
        'exceptions' => [
          '#markup' => $this->kpi((string) $exceptions, 'Uurafwijkingen', $exceptions > 0 ? 'attention' : 'positive'),
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Controle per medewerker en dag'),
        '#header' => [
          $this->t('Medewerker'),
          $this->t('Datum'),
          $this->t('Planning'),
          $this->t('Gepland'),
          $this->t('Geklokt'),
          $this->t('Verschil'),
          $this->t('Controle'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er is nog geen personeelsinzet om te controleren.'),
      ],
    ];
  }

  private function budgetHours(NodeInterface $project): float {
    $storage = $this->entityTypeManager()->getStorage('node');
    $packageIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', (int) $project->id())
      ->execute();
    if ($packageIds === []) {
      return 0.0;
    }

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
    if ($budgetIds === []) {
      return 0.0;
    }

    $lineIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_work_budget_line')
      ->condition('field_brebo_work_budget_ref', array_values($budgetIds), 'IN')
      ->execute();

    $hours = 0.0;
    foreach ($storage->loadMultiple($lineIds) as $line) {
      if ($line instanceof NodeInterface) {
        $hours += max(0.0, (float) ($line->get('field_brebo_budget_hours')->value ?? 0));
      }
    }
    return round($hours, 2);
  }

  private function stateLabel(string $state): string {
    return match ($state) {
      'future' => (string) $this->t('Toekomstig'),
      'active' => (string) $this->t('Nu actief'),
      'today_pending' => (string) $this->t('Vandaag nog niet geklokt'),
      'unclocked' => (string) $this->t('Niet geklokt'),
      'clocked_without_plan' => (string) $this->t('Geklokt zonder urennorm'),
      'match' => (string) $this->t('Volgens planning'),
      'under' => (string) $this->t('Minder dan gepland'),
      'over' => (string) $this->t('Meer dan gepland'),
      default => (string) $this->t('Planning onvolledig'),
    };
  }

  private function kpi(string $value, string $label, string $tone): string {
    return '<div class="brebo-kpi brebo-kpi--' . $tone . '"><span class="brebo-kpi__value">' . $value . '</span><span class="brebo-kpi__label">' . $label . '</span></div>';
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
