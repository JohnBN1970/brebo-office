<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_finance\Service\LabourProductivityManager;
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
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.personnel_assignment_comparison'),
      $container->get('brebo_finance.labour_productivity_manager'),
    );
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
    $weekly = [];
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
      if ($date !== '') {
        $week = (new \DateTimeImmutable($date))->format('o-\\WW');
        $weekly[$week] ??= ['planned' => 0.0, 'clocked' => 0.0, 'days' => []];
        $weekly[$week]['planned'] += $planned;
        $weekly[$week]['clocked'] += $clocked;
        $weekly[$week]['days'][$date] = TRUE;
      }
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

    $finance = $this->labourProductivity->analyzeProject($projectId);
    $financeTotals = (array) ($finance['totals'] ?? []);
    $financeBudgetHours = (float) ($financeTotals['budget_hours'] ?? 0);
    $budgetHours = $financeBudgetHours > 0 ? $financeBudgetHours : $this->budgetHours($node);
    $approvedHours = (float) ($financeTotals['actual_approved_hours'] ?? 0);
    $submittedHours = (float) ($financeTotals['actual_submitted_hours'] ?? 0);
    $financeForecastHours = (float) ($financeTotals['forecast_end_hours'] ?? 0);
    $financeForecastCost = (float) ($financeTotals['forecast_cost_ex_vat'] ?? 0);
    $financeVariance = (float) ($financeTotals['forecast_variance_ex_vat'] ?? 0);
    $forecast = $financeForecastHours > 0 ? round($financeForecastHours, 2) : round($clockedTotal + $futurePlanned, 2);
    $remainingBudget = round($budgetHours - $clockedTotal, 2);
    $completedWeeks = array_filter($weekly, static fn (array $week): bool => $week['clocked'] > 0);
    $averageClockedWeek = $completedWeeks === [] ? 0.0 : round(array_sum(array_column($completedWeeks, 'clocked')) / count($completedWeeks), 2);
    $averagePlannedWeek = $weekly === [] ? 0.0 : round(array_sum(array_column($weekly, 'planned')) / count($weekly), 2);
    $forecastDelta = round($forecast - $budgetHours, 2);
    $alerts = $this->hourAlerts(
      $budgetHours,
      $plannedTotal,
      $clockedTotal,
      $approvedHours,
      $forecast,
      $weekly,
      $exceptions,
    );

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
      'alerts' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-hours-alerts']],
        'summary' => [
          '#markup' => $this->alertSummary($alerts),
        ],
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
        'submitted' => [
          '#markup' => $this->kpi(number_format($submittedHours, 2, ',', '.') . ' u', 'Ingediend werkelijk', 'neutral'),
        ],
        'approved' => [
          '#markup' => $this->kpi(number_format($approvedHours, 2, ',', '.') . ' u', 'Goedgekeurd werkelijk', $approvedHours > $budgetHours && $budgetHours > 0 ? 'critical' : 'neutral'),
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
        'forecast_cost' => [
          '#markup' => $this->kpi('€ ' . number_format($financeForecastCost, 2, ',', '.'), 'Prognose arbeidskosten excl. btw', $financeVariance > 0 ? 'critical' : 'neutral'),
        ],
        'cost_delta' => [
          '#markup' => $this->kpi(($financeVariance > 0 ? '+€ ' : '€ ') . number_format(abs($financeVariance), 2, ',', '.'), 'Financiële afwijking excl. btw', $financeVariance > 0 ? 'critical' : ($financeVariance < 0 ? 'positive' : 'neutral')),
        ],
        'avg_week' => [
          '#markup' => $this->kpi(number_format($averageClockedWeek, 2, ',', '.') . ' u', 'Gemiddeld werkelijk per week', 'neutral'),
        ],
        'avg_plan_week' => [
          '#markup' => $this->kpi(number_format($averagePlannedWeek, 2, ',', '.') . ' u', 'Gemiddeld gepland per week', 'neutral'),
        ],
        'exceptions' => [
          '#markup' => $this->kpi((string) $exceptions, 'Uurafwijkingen', $exceptions > 0 ? 'attention' : 'positive'),
        ],
      ],
      'approval_action' => [
        '#type' => 'link',
        '#title' => $this->t('Uren beoordelen'),
        '#url' => \Drupal\Core\Url::fromRoute('brebo_inzet.project_hours_approval', ['node' => $projectId]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'finance_note' => [
        '#markup' => '<div class="messages messages--status"><strong>Financiële urenwaarheid:</strong> alleen goedgekeurde werkelijke uren tellen financieel als actual. Ingediende uren blijven zichtbaar als nog te beoordelen bewijs. Prognose en arbeidskosten komen rechtstreeks uit Finance wanneer een vergrendelde arbeidsbegroting beschikbaar is.</div>',
      ],
      'weekly' => [
        '#type' => 'table',
        '#caption' => $this->t('Uren per week'),
        '#header' => [$this->t('Week'), $this->t('Gepland'), $this->t('Werkelijk'), $this->t('Verschil'), $this->t('Gem. werkelijk per geregistreerde dag')],
        '#rows' => $this->weeklyRows($weekly),
        '#empty' => $this->t('Nog geen weekgegevens beschikbaar.'),
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

  /**
   * Builds deterministic controller alerts from the current hours truth.
   *
   * @param array<string, array{planned: float, clocked: float, days: array<string, bool>}> $weekly
   * @return list<array{level: string, title: string, detail: string}>
   */
  private function hourAlerts(
    float $budgetHours,
    float $plannedHours,
    float $clockedHours,
    float $approvedHours,
    float $forecastHours,
    array $weekly,
    int $exceptions,
  ): array {
    $alerts = [];

    if ($budgetHours > 0 && $plannedHours > $budgetHours) {
      $alerts[] = [
        'level' => 'critical',
        'title' => 'Planning boven arbeidsbegroting',
        'detail' => number_format($plannedHours - $budgetHours, 2, ',', '.') . ' uur meer gepland dan vrijgegeven.',
      ];
    }
    if ($budgetHours > 0 && $approvedHours > $budgetHours) {
      $alerts[] = [
        'level' => 'critical',
        'title' => 'Arbeidsbudget overschreden',
        'detail' => number_format($approvedHours - $budgetHours, 2, ',', '.') . ' goedgekeurde uren boven begroting.',
      ];
    }
    if ($budgetHours > 0 && $forecastHours > $budgetHours) {
      $alerts[] = [
        'level' => 'critical',
        'title' => 'Eindprognose boven arbeidsbegroting',
        'detail' => 'Verwachte overschrijding: ' . number_format($forecastHours - $budgetHours, 2, ',', '.') . ' uur.',
      ];
    }

    $latest = $weekly;
    krsort($latest);
    foreach ($latest as $week => $values) {
      if ($values['clocked'] <= 0 || $values['planned'] <= 0) {
        continue;
      }
      $delta = $values['clocked'] - $values['planned'];
      $pct = ($delta / $values['planned']) * 100;
      if ($pct > 10) {
        $alerts[] = [
          'level' => 'warning',
          'title' => 'Weekverbruik loopt uit',
          'detail' => $week . ': +' . number_format($delta, 2, ',', '.') . ' uur (' . number_format($pct, 1, ',', '.') . '% boven planning).',
        ];
      }
      break;
    }

    if ($exceptions > 0) {
      $alerts[] = [
        'level' => 'warning',
        'title' => 'Uurafwijkingen vragen controle',
        'detail' => $exceptions . ' inzetregistratie(s) wijken af van de planning of missen klokbewijs.',
      ];
    }

    if ($alerts === [] && ($clockedHours > 0 || $plannedHours > 0)) {
      $alerts[] = [
        'level' => 'ok',
        'title' => 'Uren binnen controle',
        'detail' => 'Geen actuele overschrijding of controller-signaal op basis van de beschikbare urenwaarheid.',
      ];
    }
    return $alerts;
  }

  /**
   * @param list<array{level: string, title: string, detail: string}> $alerts
   */
  private function alertSummary(array $alerts): string {
    if ($alerts === []) {
      return '<div class="messages messages--status"><strong>Urencontrole:</strong> nog onvoldoende ureninformatie voor signalering.</div>';
    }
    $items = '';
    $critical = FALSE;
    foreach ($alerts as $alert) {
      $critical = $critical || $alert['level'] === 'critical';
      $items .= '<li><strong>' . $alert['title'] . ':</strong> ' . $alert['detail'] . '</li>';
    }
    $class = $critical ? 'messages--error' : (array_filter($alerts, static fn (array $alert): bool => $alert['level'] === 'warning') ? 'messages--warning' : 'messages--status');
    return '<div class="messages ' . $class . '"><strong>Controller-signalen</strong><ul>' . $items . '</ul></div>';
  }

  /**
   * @param array<string, array{planned: float, clocked: float, days: array<string, bool>}> $weekly
   * @return array<int, array<int, string>>
   */
  private function weeklyRows(array $weekly): array {
    ksort($weekly);
    $rows = [];
    foreach ($weekly as $week => $values) {
      $delta = round($values['clocked'] - $values['planned'], 2);
      $days = count($values['days']);
      $rows[] = [
        $week,
        number_format($values['planned'], 2, ',', '.') . ' u',
        number_format($values['clocked'], 2, ',', '.') . ' u',
        ($delta > 0 ? '+' : '') . number_format($delta, 2, ',', '.') . ' u',
        number_format($days > 0 ? $values['clocked'] / $days : 0, 2, ',', '.') . ' u',
      ];
    }
    return array_reverse($rows);
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
