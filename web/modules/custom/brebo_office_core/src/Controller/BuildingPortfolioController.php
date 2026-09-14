<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Provides the building portfolio dashboard. */
final class BuildingPortfolioController extends ControllerBase {

  /** Displays the building portfolio as dashboard with map and compact table. */
  public function overview(Request $request): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = array_values($storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_building')
      ->sort('changed', 'DESC')
      ->execute());
    $buildings = $storage->loadMultiple($ids);

    $database = \Drupal::database();
    $schema = $database->schema();
    $addressCounts = [];
    $positions = [];
    $totalAddresses = 0;

    if ($ids !== [] && $schema->tableExists('brebo_building_address')) {
      $countQuery = $database->select('brebo_building_address', 'a');
      $countQuery->addField('a', 'building_nid');
      $countQuery->addExpression('COUNT(*)', 'address_count');
      $countQuery->condition('building_nid', $ids, 'IN');
      $countQuery->groupBy('building_nid');
      foreach ($countQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $buildingId = (int) $row['building_nid'];
        $count = (int) $row['address_count'];
        $addressCounts[$buildingId] = $count;
        $totalAddresses += $count;
      }

      if ($schema->fieldExists('brebo_building_address', 'latitude') && $schema->fieldExists('brebo_building_address', 'longitude')) {
        $positionQuery = $database->select('brebo_building_address', 'a');
        $positionQuery->fields('a', ['building_nid', 'latitude', 'longitude', 'is_primary']);
        $positionQuery->condition('building_nid', $ids, 'IN');
        $positionQuery->isNotNull('latitude');
        $positionQuery->isNotNull('longitude');
        $positionQuery->orderBy('is_primary', 'DESC');
        $positionQuery->orderBy('id', 'ASC');
        foreach ($positionQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
          $buildingId = (int) $row['building_nid'];
          if (!isset($positions[$buildingId])) {
            $positions[$buildingId] = $row;
          }
        }
      }
    }

    $rows = [];
    $markers = [];
    foreach ($buildings as $building) {
      if (!$building instanceof NodeInterface) {
        continue;
      }
      $buildingId = (int) $building->id();
      $dashboardUrl = Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $buildingId]);
      $truthUrl = Url::fromRoute('brebo_building_data.truth_workbench', ['node' => $buildingId]);
      $changed = \Drupal::service('date.formatter')->format($building->getChangedTime(), 'short');
      $address = $this->value($building, 'field_brebo_address');
      $city = $this->value($building, 'field_brebo_city');
      $count = $addressCounts[$buildingId] ?? 0;

      $rows[] = [
        ['data' => Link::fromTextAndUrl($building->label(), $dashboardUrl)->toRenderable()],
        $address,
        $city,
        (string) $count,
        $changed,
        ['data' => Link::fromTextAndUrl($this->t('Gebouwwaarheid'), $truthUrl)->toRenderable()],
      ];

      $position = $positions[$buildingId] ?? NULL;
      if (is_array($position)) {
        $latitude = (float) ($position['latitude'] ?? 0);
        $longitude = (float) ($position['longitude'] ?? 0);
        if ($latitude !== 0.0 || $longitude !== 0.0) {
          $markers[] = [
            'id' => $buildingId,
            'title' => $building->label(),
            'address' => $address,
            'city' => $city,
            'units' => $count,
            'lat' => $latitude,
            'lon' => $longitude,
            'url' => $dashboardUrl->toString(),
          ];
        }
      }
    }

    $mapped = count($markers);
    $total = count($rows);
    $missing = max(0, $total - $mapped);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-buildings-dashboard']],
      'toolbar' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-buildings-dashboard__toolbar']],
        'intro' => [
          '#markup' => '<div><h2>' . $this->t('Gebouwenportefeuille') . '</h2><p>' . $this->t('Overzicht van de permanente gebouwobjecten in BREBO Office.') . '</p></div>',
        ],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw gebouw'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_building']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-buildings-dashboard__kpis']],
        'buildings' => $this->kpi((string) $total, (string) $this->t('Gebouwen')),
        'addresses' => $this->kpi((string) $totalAddresses, (string) $this->t('BAG-adressen / eenheden')),
        'mapped' => $this->kpi((string) $mapped, (string) $this->t('Op kaart')),
        'missing' => $this->kpi((string) $missing, (string) $this->t('Nog zonder kaartpositie')),
      ],
      'map' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-buildings-dashboard__map'],
          'data-brebo-buildings-map' => 'true',
          'data-markers' => json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        ],
        'placeholder' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-buildings-dashboard__map-placeholder']],
          '#markup' => $markers === []
            ? '<p>' . $this->t('Nog geen gebouwen met een BAG-kaartpositie. Gebruik PDOK/BAG verversen bij een gebouw om de positie vast te leggen.') . '</p>'
            : '<p>' . $this->t('Kaart laden…') . '</p>',
        ],
      ],
      'portfolio' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-buildings-dashboard__table']],
        '#caption' => $this->t('Gebouwen'),
        '#header' => [
          $this->t('Gebouw'),
          $this->t('Adres / dossierkader'),
          $this->t('Plaats'),
          $this->t('BAG-eenheden'),
          $this->t('Gewijzigd'),
          $this->t('Openen'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen gebouwen aangemaakt.'),
      ],
      '#attached' => [
        'library' => ['brebo_office_core/building-dashboard'],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => ['node_list:brebo_building'],
        'max-age' => 0,
      ],
    ];
  }

  /** Legacy endpoint kept temporarily for backwards-compatible saved links. */
  public function move(Request $request): JsonResponse {
    return new JsonResponse(['ok' => FALSE, 'message' => 'Gebouwen gebruiken geen Kanban-workflow meer.'], 410);
  }

  /** Legacy endpoint kept temporarily for backwards-compatible saved links. */
  public function saveConfig(Request $request): JsonResponse {
    return new JsonResponse(['ok' => FALSE, 'message' => 'Kanban-instellingen zijn niet meer van toepassing op Gebouwen.'], 410);
  }

  /** @return array<string, mixed> */
  private function kpi(string $value, string $label): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-buildings-dashboard__kpi']],
      'value' => ['#plain_text' => $value, '#prefix' => '<span class="brebo-buildings-dashboard__kpi-value">', '#suffix' => '</span>'],
      'label' => ['#plain_text' => $label, '#prefix' => '<span class="brebo-buildings-dashboard__kpi-label">', '#suffix' => '</span>'],
    ];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return trim((string) ($node->get($field)->value ?? '')) ?: '—';
  }

}
