<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\WorkforceScheduleControl;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the BREBO Inzet weekly roster.
 */
final class WorkforceScheduleController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceScheduleControl $control,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_schedule_control'),
    );
  }

  public function overview(): array {
    $requested = (string) \Drupal::request()->query->get('week', 'now');
    try {
      $anchor = new \DateTimeImmutable($requested);
    }
    catch (\Exception) {
      $anchor = new \DateTimeImmutable();
    }
    $monday = $anchor->modify('monday this week')->setTime(0, 0);
    $sunday = $monday->modify('+6 days')->setTime(23, 59, 59);
    $storage = $this->entityTypeManager->getStorage('node');

    $shiftIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_shift')
      ->condition('field_brebo_shift_status', 'Vervallen', '<>')
      ->condition('field_brebo_shift_start', $sunday->format('Y-m-d\TH:i:s'), '<=')
      ->condition('field_brebo_shift_end', $monday->format('Y-m-d\TH:i:s'), '>=')
      ->sort('field_brebo_shift_start', 'ASC')
      ->execute();

    $shifts = [];
    $labels = [];
    foreach ($storage->loadMultiple($shiftIds) as $shift) {
      if (!$shift instanceof NodeInterface) {
        continue;
      }
      [$resource, $label] = $this->resource($shift, 'shift');
      $labels[$resource] = $label;
      $building = $shift->get('field_brebo_shift_building')->entity;
      $activity = $shift->get('field_brebo_shift_activity')->entity;
      $shifts[(int) $shift->id()] = [
        'resource' => $resource,
        'resource_label' => $label,
        'label' => (string) $shift->label(),
        'start' => (string) $shift->get('field_brebo_shift_start')->value,
        'end' => (string) $shift->get('field_brebo_shift_end')->value,
        'open' => (bool) ($shift->get('field_brebo_shift_open')->value ?? FALSE),
        'status' => (string) ($shift->get('field_brebo_shift_status')->value ?? '—'),
        'building' => $building instanceof NodeInterface ? (string) $building->label() : '—',
        'activity' => $activity instanceof NodeInterface ? (string) $activity->label() : '—',
        'url' => $shift->toUrl(),
      ];
    }

    $availabilityIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_availability')
      ->condition('field_brebo_avail_status', 'Bevestigd')
      ->condition('field_brebo_avail_start', $sunday->format('Y-m-d'), '<=')
      ->condition('field_brebo_avail_end', $monday->format('Y-m-d'), '>=')
      ->execute();
    $availability = [];
    foreach ($storage->loadMultiple($availabilityIds) as $period) {
      if (!$period instanceof NodeInterface) {
        continue;
      }
      [$resource] = $this->resource($period, 'avail');
      $availability[] = [
        'resource' => $resource,
        'start' => (string) $period->get('field_brebo_avail_start')->value,
        'end' => (string) $period->get('field_brebo_avail_end')->value,
        'type' => (string) $period->get('field_brebo_avail_type')->value,
      ];
    }

    $analysis = $this->control->analyze($shifts, $availability);
    $days = [];
    for ($offset = 0; $offset < 7; $offset++) {
      $day = $monday->modify("+$offset days");
      $days[$day->format('Y-m-d')] = $day;
    }

    $byResource = [];
    foreach ($analysis['shifts'] as $id => $shift) {
      $byResource[$shift['resource'] ?: 'open'][$id] = $shift;
      $labels[$shift['resource'] ?: 'open'] = $shift['resource'] ? $shift['resource_label'] : (string) $this->t('Open/onbezet');
    }
    asort($labels);

    $rows = [];
    foreach ($byResource as $resource => $resourceShifts) {
      $row = [$labels[$resource] ?? $resource];
      foreach ($days as $date => $day) {
        $items = [];
        foreach ($resourceShifts as $shift) {
          $start = new \DateTimeImmutable($shift['start']);
          $end = new \DateTimeImmutable($shift['end']);
          if ($date < $start->format('Y-m-d') || $date > $end->format('Y-m-d')) {
            continue;
          }
          $text = $start->format('H:i') . '–' . $end->format('H:i')
            . ' · ' . $shift['building'] . ' · ' . $shift['activity'];
          if ($shift['conflicts']) {
            $text .= ' ⚠ ' . implode(', ', $shift['conflicts']);
          }
          $items[] = Link::fromTextAndUrl($text, $shift['url'])->toRenderable();
        }
        $row[] = ['data' => ['#theme' => 'item_list', '#items' => $items, '#attributes' => ['class' => ['brebo-inzet-day']]]];
      }
      $rows[] = $row;
    }

    $header = [$this->t('Persoon/ploeg')];
    foreach ($days as $day) {
      $header[] = $day->format('D d-m');
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'previous' => [
          '#type' => 'link', '#title' => $this->t('← Vorige week'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule', [], ['query' => ['week' => $monday->modify('-7 days')->format('Y-m-d')]]),
          '#attributes' => ['class' => ['button']],
        ],
        'today' => [
          '#type' => 'link', '#title' => $this->t('Deze week'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule'),
          '#attributes' => ['class' => ['button']],
        ],
        'next' => [
          '#type' => 'link', '#title' => $this->t('Volgende week →'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule', [], ['query' => ['week' => $monday->modify('+7 days')->format('Y-m-d')]]),
          '#attributes' => ['class' => ['button']],
        ],
        'add' => [
          '#type' => 'link', '#title' => $this->t('Dienst toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_shift']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Week'), $this->t('Diensten'), $this->t('Open'), $this->t('Met conflict')],
        '#rows' => [[$monday->format('d-m') . ' t/m ' . $sunday->format('d-m-Y'), count($shifts), $analysis['open'], $analysis['conflicts']]],
      ],
      'schedule' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('In deze week zijn nog geen diensten gepland.'),
        '#sticky' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['url.query_args:week', 'user.permissions'],
        'tags' => ['node_list:brebo_shift', 'node_list:brebo_availability'],
        'max-age' => 300,
      ],
    ];
  }

  /**
   * @return array{0: string, 1: string}
   */
  private function resource(NodeInterface $node, string $prefix): array {
    foreach (['contact', 'user', 'team'] as $kind) {
      $field = "field_brebo_{$prefix}_$kind";
      if ($node->hasField($field) && ($entity = $node->get($field)->entity)) {
        return [$kind . ':' . $entity->id(), (string) $entity->label()];
      }
    }
    return ['', (string) $this->t('Open/onbezet')];
  }

}
