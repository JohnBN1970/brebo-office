<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\WorkforcePlanningOptimizer;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the twelve-week BREBO Inzet capacity forecast.
 */
final class WorkforceCapacityController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforcePlanningOptimizer $optimizer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_planning_optimizer'),
    );
  }

  public function overview(): array {
    $start = new \DateTimeImmutable('monday this week 00:00:00');
    $end = $start->modify('+12 weeks')->modify('-1 second');
    $raw = [];
    for ($offset = 0; $offset < 12; $offset++) {
      $week = $start->modify("+$offset weeks");
      $key = $week->format('o-\WW');
      $raw[$key] = [
        'week' => $key,
        'label' => $week->format('d-m-Y') . ' t/m ' . $week->modify('+6 days')->format('d-m-Y'),
        'demand_hours' => 0.0,
        'staffed_hours' => 0.0,
        'open_shifts' => 0,
        'conflict_shifts' => 0,
      ];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_shift')
      ->condition('field_brebo_shift_status', 'Vervallen', '<>')
      ->condition('field_brebo_shift_start', $end->format('Y-m-d\TH:i:s'), '<=')
      ->condition('field_brebo_shift_end', $start->format('Y-m-d\TH:i:s'), '>=')
      ->sort('field_brebo_shift_start', 'ASC')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $shift) {
      if (!$shift instanceof NodeInterface) {
        continue;
      }
      $shiftStart = new \DateTimeImmutable((string) $shift->get('field_brebo_shift_start')->value);
      $shiftEnd = new \DateTimeImmutable((string) $shift->get('field_brebo_shift_end')->value);
      $hours = max(0.0, (($shiftEnd->getTimestamp() - $shiftStart->getTimestamp()) / 3600)
        - ((int) ($shift->get('field_brebo_shift_break_min')->value ?? 0) / 60));
      $people = max(1, (int) ($shift->get('field_brebo_shift_people')->value ?? 1));
      $assigned = 0;
      if (!$shift->get('field_brebo_shift_contact')->isEmpty()) {
        $assigned++;
      }
      if (!$shift->get('field_brebo_shift_user')->isEmpty()) {
        $assigned++;
      }
      if ($team = $shift->get('field_brebo_shift_team')->entity) {
        $assigned += count($team->get('field_brebo_team_members')->referencedEntities());
      }

      $key = $shiftStart->format('o-\WW');
      if (!isset($raw[$key])) {
        continue;
      }
      $raw[$key]['demand_hours'] += $hours * $people;
      $raw[$key]['staffed_hours'] += $hours * min($people, $assigned);
      if ((bool) ($shift->get('field_brebo_shift_open')->value ?? FALSE) || $assigned < $people) {
        $raw[$key]['open_shifts']++;
      }
      if (in_array((string) ($shift->get('field_brebo_shift_match')->value ?? ''), ['Blokkade', 'Waarschuwing'], TRUE)) {
        $raw[$key]['conflict_shifts']++;
      }
    }

    $forecast = $this->optimizer->forecast(array_values($raw));
    $rows = [];
    $shortage = 0.0;
    foreach ($forecast as $index => $week) {
      $source = array_values($raw)[$index];
      $shortage += min(0.0, (float) $week['gap_hours']);
      $rows[] = [
        $source['label'],
        number_format((float) $week['demand_hours'], 2, ',', '.'),
        number_format((float) $week['staffed_hours'], 2, ',', '.'),
        number_format((float) $week['gap_hours'], 2, ',', '.'),
        number_format((float) $week['coverage_percent'], 1, ',', '.') . '%',
        $source['open_shifts'],
        $source['conflict_shifts'],
        $week['status'],
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'schedule' => [
          '#type' => 'link', '#title' => $this->t('Weekrooster'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule'),
          '#attributes' => ['class' => ['button']],
        ],
        'personnel' => [
          '#type' => 'link', '#title' => $this->t('Personeelsplanning'),
          '#url' => Url::fromRoute('brebo_office_core.personnel_planning'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Horizon'), $this->t('Totaal tekort'), $this->t('Uitgangspunt')],
        '#rows' => [[
          $this->t('12 weken'),
          number_format(abs($shortage), 2, ',', '.') . ' uur',
          $this->t('Gepubliceerde en conceptdiensten versus werkelijk toegewezen bezetting'),
        ]],
      ],
      'explanation' => [
        '#markup' => '<p>' . $this->t('Een tekort ontstaat wanneer de benodigde bezettingsuren groter zijn dan de aantoonbaar toegewezen uren. Open diensten en kwalificatiesignalen blijven afzonderlijk zichtbaar.') . '</p>',
      ],
      'forecast' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Week'), $this->t('Benodigd'), $this->t('Bezet'),
          $this->t('Saldo'), $this->t('Dekking'), $this->t('Open diensten'),
          $this->t('Kwalificatiesignalen'), $this->t('Status'),
        ],
        '#rows' => $rows,
        '#sticky' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_shift', 'node_list:brebo_staff_team'],
        'max-age' => 300,
      ],
    ];
  }

}
