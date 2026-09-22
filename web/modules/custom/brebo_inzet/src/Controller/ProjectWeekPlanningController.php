<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Compact project week planning based on canonical personnel assignments.
 */
final class ProjectWeekPlanningController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Weekplanning · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function overview(NodeInterface $node, Request $request): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();

    $anchor = trim((string) $request->query->get('week', ''));
    try {
      $day = $anchor !== '' ? new DrupalDateTime($anchor) : new DrupalDateTime('now');
    }
    catch (\Throwable) {
      $day = new DrupalDateTime('now');
    }
    $monday = clone $day;
    $monday->modify('monday this week');

    $days = [];
    for ($offset = 0; $offset < 5; $offset++) {
      $date = clone $monday;
      if ($offset > 0) {
        $date->modify('+' . $offset . ' days');
      }
      $days[$date->format('Y-m-d')] = $date;
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_plan_date', array_keys($days), 'IN')
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_plan_date', 'ASC')
      ->sort('field_brebo_assignment_start', 'ASC')
      ->execute();

    $byDay = array_fill_keys(array_keys($days), []);
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
      $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
      $hours = (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0);
      if (isset($byDay[$date])) {
        $byDay[$date][] = [
          'name' => $person ? $person->label() : $this->t('Onbekend'),
          'time' => trim($start . ' - ' . $end, ' -'),
          'hours' => $hours,
        ];
      }
    }

    $header = [$this->t('Medewerker')];
    foreach ($days as $date) {
      $header[] = $date->format('D d-m');
    }

    $team = $node->hasField('field_brebo_project_team')
      ? $node->get('field_brebo_project_team')->referencedEntities()
      : [];
    $rows = [];
    foreach ($team as $account) {
      if (!$account->isActive()) {
        continue;
      }
      $row = [$account->label()];
      foreach ($days as $dateKey => $date) {
        $entries = array_values(array_filter($byDay[$dateKey], static fn(array $entry): bool => $entry['name'] === $account->label()));
        if ($entries === []) {
          $row[] = '—';
          continue;
        }
        $labels = [];
        foreach ($entries as $entry) {
          $labels[] = ($entry['time'] !== '' ? $entry['time'] : '-') . ' · ' . number_format((float) $entry['hours'], 2, ',', '.') . ' u';
        }
        $row[] = implode(' / ', $labels);
      }
      $rows[] = $row;
    }

    $previous = clone $monday;
    $previous->modify('-7 days');
    $next = clone $monday;
    $next->modify('+7 days');

    $build = [
      '#cache' => [
        'contexts' => ['url.query_args:week', 'user.permissions'],
        'tags' => ['node:' . $projectId, 'node_list:brebo_personnel_assignment'],
        'max-age' => 60,
      ],
      'header' => [
        '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h1>Weekplanning</h1><p class="brebo-page-header__description">Projectteam en geplande inzet van maandag t/m vrijdag. Lege vakken zijn nog niet gepland.</p></div>',
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-action-bar']],
        'previous' => Link::fromTextAndUrl($this->t('← Vorige week'), Url::fromRoute('brebo_inzet.project_week_planning', ['node' => $projectId], ['query' => ['week' => $previous->format('Y-m-d')]]))->toRenderable(),
        'current' => ['#markup' => '<strong>Week ' . $monday->format('W') . ' · ' . $monday->format('d-m') . ' t/m ' . end($days)->format('d-m-Y') . '</strong>'],
        'next' => Link::fromTextAndUrl($this->t('Volgende week →'), Url::fromRoute('brebo_inzet.project_week_planning', ['node' => $projectId], ['query' => ['week' => $next->format('Y-m-d')]]))->toRenderable(),
        'plan' => Link::fromTextAndUrl($this->t('Medewerkers plannen'), Url::fromRoute('brebo_inzet.project_quick_planning', ['node' => $projectId]))->toRenderable(),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('Voeg eerst medewerkers toe aan het projectteam.'),
      ],
    ];
    $build['actions']['plan']['#attributes']['class'] = ['button', 'button--primary', 'brebo-button--primary'];
    return $build;
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
