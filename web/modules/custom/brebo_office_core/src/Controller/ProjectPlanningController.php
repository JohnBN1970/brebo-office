<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

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
    $build['#cache'] = [
      'contexts' => ['user.permissions'],
      'tags' => array_merge($node->getCacheTags(), ['node_list:brebo_route_item']),
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
