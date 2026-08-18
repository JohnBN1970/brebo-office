<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Controller;

use Drupal\brebo_glass\Form\GlassPositionFilterForm;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Displays the operational glass schedule.
 */
final class GlassPositionOverviewController extends ControllerBase {

  private const APPLICATION_LABELS = [
    'standard' => 'Standaard',
    'door' => 'Deur',
    'adjacent_door' => 'Naast deur',
    'low_level' => 'Laag bij vloer',
    'wet_area' => 'Natte ruimte',
    'ceiling' => 'Plafond',
    'overhead' => 'Boven personen',
    'fall_protection' => 'Doorvalbeveiliging',
    'fire_separation' => 'Brandscheiding',
  ];

  private const CHECK_LABELS = [
    'pending' => 'Nog niet gecontroleerd',
    'passed' => 'Voorcontrole akkoord',
    'expert_review' => 'Deskundige beoordeling',
    'blocked' => 'Geblokkeerd',
  ];

  private const STATUS_LABELS = [
    'concept' => 'Concept',
    'measured' => 'Ingemeten',
    'approved' => 'Technisch vrijgegeven',
    'ordered' => 'Besteld',
    'installed' => 'Gemonteerd',
  ];

  public function __construct(
    private readonly GlassPositionRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.position_repository'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  public function overview(): array {
    $request = $this->requestStack->getCurrentRequest();
    $search = trim((string) $request->query->get('q', ''));
    $status = (string) $request->query->get('status', '');
    if ($status !== '' && !isset(self::STATUS_LABELS[$status])) {
      $status = '';
    }
    $sort = (string) $request->query->get('sort', 'changed');
    $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

    $positions = $this->repository->findAll($search, $status, $sort, $direction);
    $counts = $this->repository->countByStatus();
    $storage = $this->entityTypeManager->getStorage('node');

    $nodeIds = [];
    foreach ($positions as $position) {
      $nodeIds[] = (int) $position['building_nid'];
      if (!empty($position['project_nid'])) {
        $nodeIds[] = (int) $position['project_nid'];
      }
    }
    $nodes = $storage->loadMultiple(array_unique($nodeIds));

    $rows = [];
    foreach ($positions as $position) {
      $buildingId = (int) $position['building_nid'];
      $projectId = (int) ($position['project_nid'] ?? 0);
      $rows[] = [
        'building' => isset($nodes[$buildingId])
          ? Link::createFromRoute($nodes[$buildingId]->label(), 'entity.node.canonical', ['node' => $buildingId])
          : $this->t('Gebouw #@id', ['@id' => $buildingId]),
        'project' => $projectId && isset($nodes[$projectId])
          ? Link::createFromRoute($nodes[$projectId]->label(), 'entity.node.canonical', ['node' => $projectId])
          : '-',
        'position' => $position['position_code'],
        'location' => $position['location'],
        'application' => $this->t(self::APPLICATION_LABELS[$position['application_type']] ?? $position['application_type']),
        'specification' => $position['composition'],
        'dimensions' => $position['width_mm'] . ' × ' . $position['height_mm'] . ' mm',
        'measurement_spread' => $position['width_spread_mm'] . ' / ' . $position['height_spread_mm'] . ' mm',
        'quantity' => $position['quantity'],
        'area' => number_format((float) $position['area_m2'], 3, ',', '.') . ' m²',
        'weight' => $position['estimated_weight_kg'] !== NULL
          ? number_format((float) $position['estimated_weight_kg'], 2, ',', '.') . ' kg'
          : $this->t('Te bepalen'),
        'verified' => (int) $position['measurement_verified'] === 1 ? $this->t('Ja') : $this->t('Nee'),
        'wind_advice' => $position['recommended_glass_ref'] . ' (' . number_format((float) $position['wind_utilization'] * 100, 1, ',', '.') . '%)',
        'technical_check' => $this->t(self::CHECK_LABELS[$position['technical_check_state']] ?? $position['technical_check_state']),
        'status' => $this->t(self::STATUS_LABELS[$position['technical_status']] ?? $position['technical_status']),
      ];
    }

    $build['summary'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Statusverdeling'),
      '#items' => [
        $this->t('Totaal: @count', ['@count' => $counts['all'] ?? 0]),
        $this->t('Concept: @count', ['@count' => $counts['concept'] ?? 0]),
        $this->t('Ingemeten: @count', ['@count' => $counts['measured'] ?? 0]),
        $this->t('Vrijgegeven: @count', ['@count' => $counts['approved'] ?? 0]),
        $this->t('Besteld: @count', ['@count' => $counts['ordered'] ?? 0]),
        $this->t('Gemonteerd: @count', ['@count' => $counts['installed'] ?? 0]),
      ],
      '#attributes' => ['class' => ['brebo-glass-status-summary']],
    ];
    $build['filters'] = $this->formBuilder()->getForm(GlassPositionFilterForm::class);
    $build['actions'] = [
      '#type' => 'link',
      '#title' => $this->t('Nieuwe glasopname'),
      '#url' => Url::fromRoute('brebo_glass.position_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Gebouw'),
        $this->t('Project'),
        $this->sortHeader($this->t('Positie'), 'position', $sort, $direction, $search, $status),
        $this->sortHeader($this->t('Locatie'), 'location', $sort, $direction, $search, $status),
        $this->t('Toepassing'),
        $this->t('Opbouw'),
        $this->t('Bestelmaat'),
        $this->t('Meetverschil B/H'),
        $this->t('Aantal'),
        $this->sortHeader($this->t('Oppervlak'), 'area', $sort, $direction, $search, $status),
        $this->sortHeader($this->t('Gewicht'), 'weight', $sort, $direction, $search, $status),
        $this->t('Maat gecontroleerd'),
        $this->t('Windadvies / benutting'),
        $this->t('Technische voorcontrole'),
        $this->sortHeader($this->t('Status'), 'status', $sort, $direction, $search, $status),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Geen glasposities gevonden.'),
      '#sticky' => TRUE,
    ];
    $build['notice'] = [
      '#markup' => count($positions) >= 250
        ? '<p>' . $this->t('De eerste 250 resultaten worden getoond. Verfijn de filters voor een kleinere glasstaat.') . '</p>'
        : '',
    ];

    return $build;
  }

  private function sortHeader(mixed $label, string $column, string $currentSort, string $currentDirection, string $search, string $status): Link {
    $nextDirection = $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
    return Link::fromTextAndUrl($label, Url::fromRoute('brebo_glass.position_overview', [], [
      'query' => array_filter([
        'q' => $search,
        'status' => $status,
        'sort' => $column,
        'direction' => $nextDirection,
      ], static fn(string $value): bool => $value !== ''),
    ]));
  }

}
