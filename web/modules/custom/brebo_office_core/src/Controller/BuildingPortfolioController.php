<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the building portfolio in list and configurable kanban views.
 */
final class BuildingPortfolioController extends ControllerBase {

  private const DEFAULT_COLUMNS = [
    'Mogelijk nieuw - te beoordelen',
    'Intake',
    'Actief',
    'In uitvoering',
    'Afgerond',
    'Archief',
  ];

  /**
   * Displays the building library as list or kanban.
   */
  public function overview(Request $request): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $view = in_array((string) $request->query->get('view', 'kanban'), ['list', 'kanban'], TRUE)
      ? (string) $request->query->get('view', 'kanban')
      : 'kanban';

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_building')
      ->sort('changed', 'DESC')
      ->execute();
    $buildings = $storage->loadMultiple($ids);

    $observed = [];
    foreach ($buildings as $building) {
      if ($building instanceof NodeInterface) {
        $status = $this->status($building);
        if ($status !== '') {
          $observed[$status] = $status;
        }
      }
    }

    $availableColumns = array_values(array_unique(array_merge(self::DEFAULT_COLUMNS, array_values($observed))));
    $preferences = $this->columnPreferences($availableColumns);
    $columns = $preferences['columns'];
    $hidden = $preferences['hidden'];

    $rows = [];
    $cardsByStatus = [];
    foreach ($availableColumns as $column) {
      $cardsByStatus[$column] = [];
    }

    foreach ($buildings as $building) {
      if (!$building instanceof NodeInterface) {
        continue;
      }
      $status = $this->status($building);
      if ($status === '') {
        $status = 'Intake';
      }
      if (!isset($cardsByStatus[$status])) {
        $cardsByStatus[$status] = [];
        $columns[] = $status;
      }

      $dashboardUrl = Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()]);
      $editUrl = Url::fromRoute('entity.node.edit_form', ['node' => $building->id()]);
      $changed = \Drupal::service('date.formatter')->format($building->getChangedTime(), 'short');
      $address = $this->value($building, 'field_brebo_address');

      $rows[] = [
        ['data' => Link::fromTextAndUrl($building->label(), $dashboardUrl)->toRenderable()],
        $status,
        $changed,
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), $editUrl)->toRenderable()],
      ];

      $cardsByStatus[$status][] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-building-kanban__card', 'brebo-kanban-card'],
          'draggable' => 'true',
          'data-building-id' => (string) $building->id(),
          'data-building-status' => $status,
        ],
        'title' => [
          '#type' => 'link',
          '#title' => $building->label(),
          '#url' => $dashboardUrl,
          '#attributes' => ['class' => ['brebo-building-kanban__card-title']],
        ],
        'address' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $address,
          '#attributes' => ['class' => ['brebo-building-kanban__meta']],
        ],
        'changed' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => (string) $this->t('Gewijzigd @date', ['@date' => $changed]),
          '#attributes' => ['class' => ['brebo-building-kanban__meta']],
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-building-kanban__card-actions']],
          'open' => Link::fromTextAndUrl($this->t('Openen'), $dashboardUrl)->toRenderable(),
          'edit' => Link::fromTextAndUrl($this->t('Bewerken'), $editUrl)->toRenderable(),
        ],
      ];
    }

    $kanban = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['brebo-building-kanban'],
        'data-building-kanban' => 'true',
        'data-move-url' => Url::fromRoute('brebo_office_core.buildings_kanban_move')->toString(),
        'data-config-url' => Url::fromRoute('brebo_office_core.buildings_kanban_config')->toString(),
      ],
      '#access' => $view === 'kanban',
    ];

    foreach (array_values(array_unique($columns)) as $column) {
      $isHidden = in_array($column, $hidden, TRUE);
      $cards = $cardsByStatus[$column] ?? [];
      $kanban['column_' . count($kanban)] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => array_values(array_filter([
            'brebo-building-kanban__column',
            'brebo-kanban-column',
            $isHidden ? 'is-kanban-column-hidden' : NULL,
          ])),
          'data-kanban-status' => $column,
          'data-kanban-hidden' => $isHidden ? 'true' : 'false',
        ],
        'header' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-building-kanban__column-header']],
          'drag' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => '⋮⋮',
            '#attributes' => ['class' => ['brebo-building-kanban__column-handle'], 'title' => $this->t('Kolom verslepen')],
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $column,
          ],
          'count' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => (string) count($cards),
            '#attributes' => ['class' => ['brebo-building-kanban__count']],
          ],
          'hide' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $isHidden ? (string) $this->t('Tonen') : (string) $this->t('Verbergen'),
            '#attributes' => [
              'type' => 'button',
              'class' => ['brebo-building-kanban__visibility'],
              'data-kanban-toggle-column' => $column,
              'aria-label' => $isHidden
                ? (string) $this->t('Kolom @column tonen', ['@column' => $column])
                : (string) $this->t('Kolom @column verbergen', ['@column' => $column]),
            ],
          ],
        ],
        'cards' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-building-kanban__cards', 'brebo-kanban-cards'], 'data-kanban-dropzone' => $column],
        ] + $cards,
      ];
    }

    $visibleQuery = $request->query->all();
    $listQuery = $visibleQuery;
    $listQuery['view'] = 'list';
    $kanbanQuery = $visibleQuery;
    $kanbanQuery['view'] = 'kanban';

    return [
      'toolbar' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-building-portfolio__toolbar']],
        'views' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-view-switcher'], 'aria-label' => $this->t('Weergave kiezen')],
          'list' => [
            '#type' => 'link',
            '#title' => $this->t('Lijst'),
            '#url' => Url::fromRoute('brebo_office_core.buildings', [], ['query' => $listQuery]),
            '#attributes' => ['class' => ['button', $view === 'list' ? 'is-active' : '']],
          ],
          'kanban' => [
            '#type' => 'link',
            '#title' => $this->t('Kanban'),
            '#url' => Url::fromRoute('brebo_office_core.buildings', [], ['query' => $kanbanQuery]),
            '#attributes' => ['class' => ['button', $view === 'kanban' ? 'is-active' : '']],
          ],
        ],
        'configure' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Kolommen indelen'),
          '#attributes' => [
            'type' => 'button',
            'class' => ['button', 'brebo-building-kanban__configure'],
            'data-kanban-configure' => 'true',
          ],
          '#access' => $view === 'kanban',
        ],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw gebouw'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_building']),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'config_help' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-building-kanban__config-help'], 'hidden' => 'hidden'],
        'text' => [
          '#markup' => '<p>' . $this->t('Sleep kolommen in de gewenste volgorde en verberg kolommen die je niet gebruikt. Deze indeling wordt voor jouw gebruiker opgeslagen.') . '</p>',
        ],
      ],
      'list' => [
        '#type' => 'table',
        '#header' => [$this->t('Naam'), $this->t('Status'), $this->t('Gewijzigd'), $this->t('Actie')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen gebouwen aangemaakt.'),
        '#access' => $view === 'list',
      ],
      'kanban' => $kanban,
      '#attached' => [
        'library' => ['brebo_office_core/building-kanban'],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.query_args:view'],
        'tags' => ['node_list:brebo_building'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Moves a building to a workflow column.
   */
  public function move(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    $buildingId = (int) ($payload['building_id'] ?? 0);
    $status = trim((string) ($payload['status'] ?? ''));
    if ($buildingId <= 0 || $status === '') {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Ongeldige verplaatsing.'], 400);
    }

    $building = $this->entityTypeManager()->getStorage('node')->load($buildingId);
    if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building') {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Gebouw niet gevonden.'], 404);
    }
    if (!$building->access('update', $this->currentUser())) {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Geen wijzigingsrecht voor dit gebouw.'], 403);
    }
    if (!$building->hasField('field_brebo_status')) {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Gebouwstatus ontbreekt.'], 409);
    }

    $previous = $this->status($building);
    if ($previous !== $status) {
      $building->set('field_brebo_status', $status);
      $building->save();
    }

    return new JsonResponse(['ok' => TRUE, 'building_id' => $buildingId, 'status' => $status, 'previous_status' => $previous]);
  }

  /**
   * Stores a user's column order and visibility without schema changes.
   */
  public function saveConfig(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    $columns = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['columns'] ?? [])))));
    $hidden = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['hidden'] ?? [])))));
    if ($columns === []) {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Geen kolommen ontvangen.'], 400);
    }

    \Drupal::service('user.data')->set('brebo_office_core', (int) $this->currentUser()->id(), 'building_kanban', [
      'columns' => $columns,
      'hidden' => $hidden,
    ]);

    return new JsonResponse(['ok' => TRUE, 'columns' => $columns, 'hidden' => $hidden]);
  }

  /**
   * Returns normalized per-user kanban preferences.
   *
   * @return array{columns: string[], hidden: string[]}
   */
  private function columnPreferences(array $available): array {
    $stored = \Drupal::service('user.data')->get('brebo_office_core', (int) $this->currentUser()->id(), 'building_kanban');
    $storedColumns = is_array($stored) ? array_values(array_filter(array_map('strval', (array) ($stored['columns'] ?? [])))) : [];
    $storedHidden = is_array($stored) ? array_values(array_filter(array_map('strval', (array) ($stored['hidden'] ?? [])))) : [];

    $columns = [];
    foreach ($storedColumns as $column) {
      if (in_array($column, $available, TRUE)) {
        $columns[] = $column;
      }
    }
    foreach ($available as $column) {
      if (!in_array($column, $columns, TRUE)) {
        $columns[] = $column;
      }
    }

    return [
      'columns' => $columns,
      'hidden' => array_values(array_intersect($storedHidden, $columns)),
    ];
  }

  private function status(NodeInterface $building): string {
    if (!$building->hasField('field_brebo_status') || $building->get('field_brebo_status')->isEmpty()) {
      return '';
    }
    return trim((string) $building->get('field_brebo_status')->value);
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return trim((string) ($node->get($field)->value ?? '')) ?: '—';
  }

}
