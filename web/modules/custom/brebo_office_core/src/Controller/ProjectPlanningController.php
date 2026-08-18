<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the first operational project planning view.
 */
final class ProjectPlanningController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Planning — @project', ['@project' => $node->label()]);
  }

  /**
   * Shows ordered project route steps as phase and milestone planning.
   */
  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $storage = $this->entityTypeManager()->getStorage('node');

    $activity_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_plan_activity')
      ->condition('field_brebo_project_ref.target_id', $node->id())
      ->sort('field_brebo_plan_phase', 'ASC')
      ->sort('field_brebo_plan_sequence', 'ASC')
      ->sort('field_brebo_plan_start', 'ASC')
      ->execute();
    $activity_groups = [];
    $dependency_rows = [];
    $activity_counts = ['total' => 0, 'done' => 0, 'late' => 0, 'blocked' => 0, 'critical' => 0];
    $today = date('Y-m-d');
    $gantt_source = [];
    $gantt_dates = [];
    foreach ($storage->loadMultiple($activity_ids) as $activity) {
      if (!$activity instanceof NodeInterface) {
        continue;
      }
      $status = $this->value($activity, 'field_brebo_plan_status');
      $start = $this->value($activity, 'field_brebo_plan_start');
      $end = $this->value($activity, 'field_brebo_plan_end');
      $baseline_end = $this->value($activity, 'field_brebo_plan_baseline_end');
      $progress = (float) ($activity->get('field_brebo_plan_progress')->value ?? 0);
      $done = $status === 'Gereed' || $progress >= 100;
      $blocked = $status === 'Geblokkeerd';
      $late = !$done && $end !== '—' && $end < $today;
      $critical = (bool) ($activity->get('field_brebo_plan_critical')->value ?? FALSE);
      $activity_counts['total']++;
      $activity_counts['done'] += $done ? 1 : 0;
      $activity_counts['late'] += $late ? 1 : 0;
      $activity_counts['blocked'] += $blocked ? 1 : 0;
      $activity_counts['critical'] += $critical ? 1 : 0;

      $object = $activity->get('field_brebo_plan_object_ref')->entity;
      $building = $activity->get('field_brebo_building_ref')->entity;
      $owner = $activity->get('field_brebo_plan_owner')->entity;
      $supplier = $activity->get('field_brebo_plan_supplier')->entity;
      $predecessors = $activity->get('field_brebo_plan_predecessors')->referencedEntities();
      $predecessor_labels = array_map(
        static fn (NodeInterface $predecessor): string => (string) $predecessor->label(),
        array_filter($predecessors, static fn ($predecessor): bool => $predecessor instanceof NodeInterface)
      );
      $relation = $this->value($activity, 'field_brebo_plan_relation');
      $lag = (int) ($activity->get('field_brebo_plan_lag_days')->value ?? 0);
      foreach ($predecessors as $predecessor) {
        if (!$predecessor instanceof NodeInterface) {
          continue;
        }
        $dependency_rows[] = [
          ['data' => Link::fromTextAndUrl($predecessor->label(), $predecessor->toUrl())->toRenderable()],
          ['data' => Link::fromTextAndUrl($activity->label(), $activity->toUrl())->toRenderable()],
          $relation,
          $lag === 0 ? $this->t('Geen') : $this->t('@days werkdagen', ['@days' => $lag]),
          $critical ? $this->t('Kritiek') : $this->t('Niet kritiek'),
          $status,
        ];
      }
      $variance = '—';
      if ($baseline_end !== '—' && $end !== '—') {
        $baseline = new \DateTimeImmutable($baseline_end);
        $current = new \DateTimeImmutable($end);
        $days = (int) $baseline->diff($current)->format('%r%a');
        $variance = $days === 0 ? (string) $this->t('0 dagen') : (string) $this->t('@days dagen', ['@days' => $days]);
      }
      $signal = $blocked ? (string) $this->t('Geblokkeerd')
        : ($late ? (string) $this->t('Te laat') : ($done ? (string) $this->t('Gereed') : ($critical ? (string) $this->t('Kritiek') : (string) $this->t('Op schema'))));
      $baseline_start = $this->value($activity, 'field_brebo_plan_baseline_start');
      $is_milestone = (bool) ($activity->get('field_brebo_plan_milestone')->value ?? FALSE);
      foreach ([$start, $end, $baseline_start, $baseline_end] as $date_value) {
        if ($date_value !== '—') {
          $gantt_dates[] = new \DateTimeImmutable($date_value);
        }
      }
      $gantt_source[] = [
        'label' => (string) $activity->label(),
        'code' => $this->value($activity, 'field_brebo_plan_code'),
        'phase' => $this->value($activity, 'field_brebo_plan_phase'),
        'start' => $start,
        'end' => $end,
        'baseline_start' => $baseline_start,
        'baseline_end' => $baseline_end,
        'progress' => max(0.0, min(100.0, $progress)),
        'critical' => $critical,
        'late' => $late,
        'milestone' => $is_milestone,
        'url' => $activity->toUrl(),
      ];
      $phase = $this->value($activity, 'field_brebo_plan_phase');
      $activity_groups[$phase][] = [
        $this->value($activity, 'field_brebo_plan_code'),
        ['data' => Link::fromTextAndUrl($activity->label(), $activity->toUrl())->toRenderable()],
        $building instanceof NodeInterface ? $building->label() : '—',
        $object instanceof NodeInterface ? $object->label() : '—',
        $start,
        $end,
        $this->value($activity, 'field_brebo_plan_duration'),
        number_format($progress, 0, ',', '.') . '%',
        $variance,
        implode(', ', $predecessor_labels) ?: '—',
        $this->value($activity, 'field_brebo_plan_relation'),
        $owner ? $owner->label() : '—',
        $supplier instanceof NodeInterface ? $supplier->label() : '—',
        $signal,
        ['data' => Link::fromTextAndUrl(
          $this->t('Bijwerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $activity->id()])
        )->toRenderable()],
      ];
    }

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_route_item')
      ->condition('field_brebo_project_ref.target_id', $node->id())
      ->sort('field_brebo_route_sequence', 'ASC')
      ->sort('field_brebo_route_due', 'ASC')
      ->execute();

    $today = date('Y-m-d');
    $counts = ['total' => 0, 'done' => 0, 'late' => 0, 'blocked' => 0];
    $groups = [];
    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof NodeInterface) {
        continue;
      }
      $status = $this->value($item, 'field_brebo_route_status');
      $due = $this->value($item, 'field_brebo_route_due');
      $done = in_array($status, ['Gereed', 'N.V.T.'], TRUE);
      $blocked = $status === 'Geblokkeerd';
      $late = !$done && $due !== '—' && $due < $today;
      $counts['total']++;
      $counts['done'] += $done ? 1 : 0;
      $counts['blocked'] += $blocked ? 1 : 0;
      $counts['late'] += $late ? 1 : 0;

      $phase = $this->value($item, 'field_brebo_lens_domain');
      $owner = $item->hasField('field_brebo_route_owner')
        ? $item->get('field_brebo_route_owner')->entity
        : NULL;
      $signal = $blocked ? (string) $this->t('Geblokkeerd')
        : ($late ? (string) $this->t('Te laat') : ($done ? (string) $this->t('Gereed') : (string) $this->t('Op schema')));

      $groups[$phase][] = [
        $this->value($item, 'field_brebo_route_sequence'),
        ['data' => Link::fromTextAndUrl($item->label(), $item->toUrl())->toRenderable()],
        $this->value($item, 'field_brebo_route_kind'),
        $owner ? $owner->label() : '—',
        $due,
        $status,
        $signal,
        $this->value($item, 'field_brebo_route_evidence'),
        ['data' => Link::fromTextAndUrl(
          $this->t('Bijwerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $item->id()])
        )->toRenderable()],
      ];
    }

    $percent = $counts['total'] > 0
      ? (int) round(($counts['done'] / $counts['total']) * 100)
      : 0;
    $build = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'project' => [
          '#type' => 'link', '#title' => $this->t('Terug naar project'),
          '#url' => Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'activity' => [
          '#type' => 'link', '#title' => $this->t('Activiteit toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_plan_activity'], [
            'query' => ['project' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'recalculate' => [
          '#type' => 'link', '#title' => $this->t('Planning doorrekenen'),
          '#url' => Url::fromRoute('brebo_office_core.project_planning_recalculate', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'add' => [
          '#type' => 'link', '#title' => $this->t('Planningsstap toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_route_item'], [
            'query' => ['project' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Voortgang'), $this->t('Stappen'), $this->t('Gereed'), $this->t('Te laat'), $this->t('Geblokkeerd')],
        '#rows' => [[$percent . '%', $counts['total'], $counts['done'], $counts['late'], $counts['blocked']]],
      ],
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => ['#markup' => $this->t('<strong>Planningsbasis:</strong> projectroute, mijlpalen en vrijgaven vormen de bestuurlijke ruggengraat. Uitvoeringsactiviteiten worden hierna gekoppeld aan gebouwobjecten en deze vaste stappen.')],
      ],
    ];

    $activity_percent = $activity_counts['total'] > 0
      ? (int) round(($activity_counts['done'] / $activity_counts['total']) * 100)
      : 0;
    $build['activities_heading'] = [
      '#markup' => '<h2>' . $this->t('Uitvoeringsplanning') . '</h2>',
    ];
    $build['activities_summary'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['brebo-calc-summary']],
      '#header' => [$this->t('Voortgang'), $this->t('Activiteiten'), $this->t('Gereed'), $this->t('Te laat'), $this->t('Geblokkeerd'), $this->t('Kritiek')],
      '#rows' => [[$activity_percent . '%', $activity_counts['total'], $activity_counts['done'], $activity_counts['late'], $activity_counts['blocked'], $activity_counts['critical']]],
    ];
    if ($gantt_dates) {
      usort($gantt_dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);
      $range_start = $gantt_dates[0];
      $range_end = $gantt_dates[count($gantt_dates) - 1];
      $range_days = max(1, (int) $range_start->diff($range_end)->format('%a') + 1);
      $gantt_rows = [];
      foreach ($gantt_source as $source) {
        if ($source['start'] === '—' || $source['end'] === '—') {
          continue;
        }
        $start_date = new \DateTimeImmutable($source['start']);
        $end_date = new \DateTimeImmutable($source['end']);
        $offset = max(0, (int) $range_start->diff($start_date)->format('%r%a'));
        $duration = max(1, (int) $start_date->diff($end_date)->format('%a') + 1);
        $start_percent = ($offset / $range_days) * 100;
        $span_percent = ($duration / $range_days) * 100;
        $classes = ['brebo-gantt__row'];
        $classes[] = $source['critical'] ? 'is-critical' : 'is-normal';
        if ($source['late']) {
          $classes[] = 'is-late';
        }

        $baseline = NULL;
        if ($source['baseline_start'] !== '—' && $source['baseline_end'] !== '—') {
          $baseline_start_date = new \DateTimeImmutable($source['baseline_start']);
          $baseline_end_date = new \DateTimeImmutable($source['baseline_end']);
          $baseline_offset = max(0, (int) $range_start->diff($baseline_start_date)->format('%r%a'));
          $baseline_duration = max(1, (int) $baseline_start_date->diff($baseline_end_date)->format('%a') + 1);
          $baseline = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['brebo-gantt__baseline'],
              'style' => sprintf('--gantt-start: %.4f; --gantt-span: %.4f;', ($baseline_offset / $range_days) * 100, ($baseline_duration / $range_days) * 100),
              'title' => (string) $this->t('Baseline @start t/m @end', ['@start' => $source['baseline_start'], '@end' => $source['baseline_end']]),
            ],
          ];
        }
        $bar = $source['milestone']
          ? [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['brebo-gantt__milestone'],
              'style' => sprintf('--gantt-start: %.4f;', $start_percent),
              'title' => (string) $this->t('Mijlpaal op @date', ['@date' => $source['start']]),
            ],
          ]
          : [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['brebo-gantt__bar'],
              'style' => sprintf('--gantt-start: %.4f; --gantt-span: %.4f; --gantt-progress: %.2f;', $start_percent, $span_percent, $source['progress']),
              'title' => (string) $this->t('@start t/m @end — @progress%', ['@start' => $source['start'], '@end' => $source['end'], '@progress' => number_format($source['progress'], 0, ',', '.')]),
            ],
          ];
        $gantt_rows[] = [
          '#type' => 'container',
          '#attributes' => ['class' => $classes],
          'label' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['brebo-gantt__label']],
            'link' => Link::fromTextAndUrl($source['label'], $source['url'])->toRenderable(),
            'meta' => ['#markup' => '<small>' . Html::escape($source['code'] . ' · ' . $source['phase']) . '</small>'],
          ],
          'track' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['brebo-gantt__track']],
            'baseline' => $baseline ?? ['#markup' => ''],
            'bar' => $bar,
          ],
        ];
      }
      $build['gantt_heading'] = ['#markup' => '<h2>' . $this->t('Gantt-planning') . '</h2>'];
      $build['gantt_legend'] = [
        '#markup' => '<p>' . $this->t('Grijs = baseline · groen = actueel · rood = kritisch · ruit = mijlpaal') . '</p>',
      ];
      $build['gantt'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-gantt']],
        'axis' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-gantt__axis']],
          'label' => ['#markup' => '<div class="brebo-gantt__axis-label">' . $this->t('Activiteit') . '</div>'],
          'range' => ['#markup' => '<div class="brebo-gantt__axis-range"><span>' . $range_start->format('d-m-Y') . '</span><span>' . $range_end->format('d-m-Y') . '</span></div>'],
        ],
        'rows' => $gantt_rows,
      ];
    }

    foreach ($activity_groups as $phase => $activity_rows) {
      $build['activities_' . count($build)] = [
        '#type' => 'details',
        '#title' => $this->t('@phase — @count activiteiten', ['@phase' => $phase, '@count' => count($activity_rows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Code'), $this->t('Activiteit'), $this->t('Gebouw'), $this->t('Object'),
            $this->t('Start'), $this->t('Gereed'), $this->t('Duur'), $this->t('Voortgang'),
            $this->t('T.o.v. baseline'), $this->t('Voorgangers'), $this->t('Relatie'),
            $this->t('Verantwoordelijke'), $this->t('Uitvoerder'), $this->t('Signaal'), $this->t('Actie'),
          ],
          '#rows' => $activity_rows,
          '#sticky' => TRUE,
        ],
      ];
    }
    if (!$activity_groups) {
      $build['activities_empty'] = [
        '#markup' => '<p>' . $this->t('Nog geen uitvoeringsactiviteiten gepland.') . '</p>',
      ];
    }

    $build['dependencies_heading'] = [
      '#markup' => '<h2>' . $this->t('Relatie-overzicht') . '</h2>',
    ];
    $build['dependencies'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Voorganger'), $this->t('Opvolger'), $this->t('Relatie'),
        $this->t('Wachttijd/overlap'), $this->t('Pad'), $this->t('Status opvolger'),
      ],
      '#rows' => $dependency_rows,
      '#empty' => $this->t('Nog geen relaties tussen planningsactiviteiten vastgelegd.'),
      '#sticky' => TRUE,
    ];

    $build['route_heading'] = [
      '#markup' => '<h2>' . $this->t('Projectroute, mijlpalen en vrijgaven') . '</h2>',
    ];
    foreach ($groups as $phase => $rows) {
      $build['phase_' . count($build)] = [
        '#type' => 'details',
        '#title' => $this->t('@phase — @count stappen', ['@phase' => $phase, '@count' => count($rows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Volgorde'), $this->t('Stap/mijlpaal'), $this->t('Soort'), $this->t('Verantwoordelijke'), $this->t('Streefdatum'), $this->t('Status'), $this->t('Signaal'), $this->t('Bewijs'), $this->t('Actie')],
          '#rows' => $rows,
          '#sticky' => TRUE,
        ],
      ];
    }
    if (!$groups) {
      $build['empty'] = ['#markup' => '<p>' . $this->t('Nog geen projectroute of mijlpalen gepland.') . '</p>'];
    }
    $build['#attached']['library'][] = 'brebo_office/project-gantt';
    $build['#cache'] = [
      'contexts' => ['user.permissions'],
      'tags' => array_merge($node->getCacheTags(), ['node_list:brebo_route_item', 'node_list:brebo_plan_activity']),
      'max-age' => 3600,
    ];
    return $build;
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return (string) ($node->get($field)->value ?? '—');
  }

}
