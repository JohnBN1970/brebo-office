<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Controller;

use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\brebo_building_data\Service\BuildingRelationRepository;
use Drupal\brebo_building_data\Service\BuildingTruthRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildingTruthWorkbenchController extends ControllerBase {

  public function __construct(
    private readonly BuildingTruthRepository $truth,
    private readonly BuildingObjectRepository $objects,
    private readonly BuildingRelationRepository $relations,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_building_data.truth_repository'),
      $container->get('brebo_building_data.object_repository'),
      $container->get('brebo_building_data.repository'),
      $container->get('date.formatter'),
    );
  }

  public function title(NodeInterface $node): string {
    return 'Gebouwwaarheid - ' . $node->label();
  }

  public function workbench(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('view', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $buildingNid = (int) $node->id();
    $canEdit = $node->access('update', $this->currentUser());
    $facts = $this->truth->currentFacts($buildingNid);
    $pending = $this->truth->pendingProposals($buildingNid);
    $objectMap = $this->objectMap($this->objects->tree($buildingNid));
    $addresses = array_values($this->relations->addressesForBuilding($buildingNid));
    $bagIdentities = array_values($this->relations->bagIdentitiesForBuilding($buildingNid));

    $pandIds = [];
    $identityBySourceRef = [];
    foreach ($bagIdentities as $identity) {
      $bagType = (string) ($identity['bag_type'] ?? '');
      $bagId = trim((string) ($identity['bag_id'] ?? ''));
      if ($bagType === 'pand' && $bagId !== '') {
        $pandIds[] = $bagId;
      }
      $sourceRef = trim((string) ($identity['source_ref'] ?? ''));
      if ($sourceRef !== '' && $bagId !== '' && in_array($bagType, ['verblijfsobject', 'adresseerbaarobject', 'nummeraanduiding'], TRUE)) {
        $identityBySourceRef[$sourceRef][$bagType] = $bagId;
      }
    }
    $pandIds = array_values(array_unique($pandIds));

    usort($addresses, static function(array $a, array $b): int {
      $streetCompare = strnatcasecmp((string) ($a['street'] ?? ''), (string) ($b['street'] ?? ''));
      if ($streetCompare !== 0) {
        return $streetCompare;
      }
      return strnatcasecmp(
        trim((string) ($a['house_number'] ?? '') . (string) ($a['house_letter'] ?? '') . (string) ($a['addition'] ?? '')),
        trim((string) ($b['house_number'] ?? '') . (string) ($b['house_letter'] ?? '') . (string) ($b['addition'] ?? '')),
      );
    });

    $addressRows = [];
    foreach ($addresses as $address) {
      $number = trim(implode('', [
        (string) ($address['house_number'] ?? ''),
        (string) ($address['house_letter'] ?? ''),
        (string) ($address['addition'] ?? ''),
      ]));
      $sourceRef = trim((string) ($address['source_ref'] ?? ''));
      $unitIdentity = $identityBySourceRef[$sourceRef] ?? [];
      $vboId = trim((string) ($unitIdentity['verblijfsobject'] ?? $unitIdentity['adresseerbaarobject'] ?? ''));
      $numberDesignationId = trim((string) ($unitIdentity['nummeraanduiding'] ?? ''));
      $addressRows[] = [
        trim((string) ($address['street'] ?? '')) ?: '—',
        $number ?: '—',
        trim((string) ($address['postal_code'] ?? '')) ?: '—',
        trim((string) ($address['city'] ?? '')) ?: '—',
        $vboId ?: '—',
        $numberDesignationId ?: '—',
        !empty($address['is_primary']) ? $this->t('Hoofdadres') : $this->t('Eenheid'),
        trim((string) ($address['source'] ?? '')) ?: '—',
      ];
    }

    $factRows = [];
    $historyRows = [];
    foreach ($facts as $fact) {
      $objectId = isset($fact['object_id']) && $fact['object_id'] !== NULL ? (int) $fact['object_id'] : NULL;
      $scope = $objectId === NULL ? 'Gebouw' : ($objectMap[$objectId] ?? ('Object #' . $objectId));
      $factRows[] = [
        $scope,
        (string) $fact['fact_key'],
        $this->renderValue($fact['value'] ?? NULL),
        'v' . (int) ($fact['version'] ?? 1),
        $this->date((int) ($fact['verified_at'] ?? 0)),
        $this->source($fact),
      ];
      foreach ($this->truth->history($buildingNid, (string) $fact['fact_key'], $objectId) as $history) {
        $historyRows[] = [
          $scope,
          (string) $history['fact_key'],
          $this->renderValue($history['value'] ?? NULL),
          'v' . (int) ($history['version'] ?? 1),
          $this->date((int) ($history['valid_from'] ?? 0)),
          $this->date((int) ($history['valid_to'] ?? 0)),
          $this->source($history),
        ];
      }
    }

    $proposalRows = [];
    foreach ($pending as $proposal) {
      $objectId = isset($proposal['object_id']) && $proposal['object_id'] !== NULL ? (int) $proposal['object_id'] : NULL;
      $scope = $objectId === NULL ? 'Gebouw' : ($objectMap[$objectId] ?? ('Object #' . $objectId));
      $review = $canEdit
        ? Link::fromTextAndUrl($this->t('Beoordelen'), Url::fromRoute('brebo_building_data.truth_proposal_review', [
          'node' => $buildingNid,
          'proposal' => (int) $proposal['id'],
        ]))->toRenderable()
        : ['#markup' => '—'];
      $proposalRows[] = [
        '#' . (int) $proposal['id'],
        $scope,
        (string) $proposal['fact_key'],
        $this->renderValue($proposal['value'] ?? NULL),
        $this->source($proposal),
        $this->date((int) ($proposal['proposed_at'] ?? 0)),
        trim((string) ($proposal['reason'] ?? '')) ?: '—',
        ['data' => $review],
      ];
    }

    return [
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => [
          '#markup' => '<strong>Gebouwwaarheid</strong><br>Dit dossier toont alleen geverifieerde actuele waarheid. Projectwijzigingen worden eerst als revisievoorstel aangeboden; na verificatie wordt de vorige toestand automatisch historie.',
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'dashboard' => Link::fromTextAndUrl($this->t('Gebouw openen'), Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $buildingNid]))->toRenderable(),
        'base_edit' => [
          '#type' => 'link',
          '#title' => $this->t('Basisgegevens bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $buildingNid], ['query' => ['brebo_base_edit' => 1]]),
          '#attributes' => ['class' => ['button']],
          '#access' => $canEdit,
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#caption' => $this->t('Gebouw'),
        '#header' => [$this->t('Onderdeel'), $this->t('Waarde')],
        '#rows' => [
          [$this->t('Naam'), $node->label()],
          [$this->t('BAG-pand'), $pandIds === [] ? '—' : implode(', ', $pandIds)],
          [$this->t('Adressen / eenheden'), (string) count($addresses)],
          [$this->t('Actuele feiten'), (string) count($facts)],
          [$this->t('Open revisievoorstellen'), (string) count($pending)],
        ],
      ],
      'bag_units' => [
        '#type' => 'table',
        '#caption' => $this->t('BAG-adressen en woningnummers'),
        '#header' => [
          $this->t('Straat'),
          $this->t('Huis-/woningnummer'),
          $this->t('Postcode'),
          $this->t('Plaats'),
          $this->t('BAG-verblijfsobject'),
          $this->t('BAG-nummeraanduiding'),
          $this->t('Relatie'),
          $this->t('Bron'),
        ],
        '#rows' => $addressRows,
        '#empty' => $this->t('Nog geen BAG-adressen of woningnummers gekoppeld. PDOK vult deze automatisch zodra het gebouwadres kan worden herkend.'),
      ],
      'truth' => [
        '#type' => 'table',
        '#caption' => $this->t('Actuele geverifieerde waarheid'),
        '#header' => [$this->t('Scope'), $this->t('Feit'), $this->t('Waarde'), $this->t('Versie'), $this->t('Geverifieerd'), $this->t('Bron')],
        '#rows' => $factRows,
        '#empty' => $this->t('Voor dit gebouw is nog geen geverifieerde gebouwwaarheid vastgelegd.'),
      ],
      'pending' => [
        '#type' => 'table',
        '#caption' => $this->t('Openstaande revisievoorstellen'),
        '#header' => [$this->t('ID'), $this->t('Scope'), $this->t('Feit'), $this->t('Voorgestelde waarde'), $this->t('Bron'), $this->t('Voorgesteld'), $this->t('Reden'), $this->t('Actie')],
        '#rows' => $proposalRows,
        '#empty' => $this->t('Er staan geen wijzigingen te wachten op verificatie.'),
      ],
      'history' => [
        '#type' => 'table',
        '#caption' => $this->t('Gebouwhistorie'),
        '#header' => [$this->t('Scope'), $this->t('Feit'), $this->t('Historische waarde'), $this->t('Versie'), $this->t('Geldig vanaf'), $this->t('Geldig tot'), $this->t('Bron')],
        '#rows' => $historyRows,
        '#empty' => $this->t('Nog geen vervangen gebouwwaarheid; historie ontstaat zodra een geverifieerde waarheid wordt opgevolgd.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function objectMap(array $tree): array {
    $map = [];
    $walk = function(array $nodes, string $prefix = '') use (&$walk, &$map): void {
      foreach ($nodes as $node) {
        $id = (int) ($node['id'] ?? 0);
        $label = trim((string) ($node['label'] ?? 'Object #' . $id));
        $path = $prefix === '' ? $label : $prefix . ' / ' . $label;
        if ($id > 0) $map[$id] = $path;
        $walk((array) ($node['children'] ?? []), $path);
      }
    };
    $walk($tree);
    return $map;
  }

  private function renderValue(mixed $value): string {
    if (is_bool($value)) return $value ? 'Ja' : 'Nee';
    if (is_scalar($value) || $value === NULL) return $value === NULL ? '—' : (string) $value;
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
  }

  private function source(array $row): string {
    $parts = array_filter([
      trim((string) ($row['source_type'] ?? '')),
      isset($row['source_project_nid']) && $row['source_project_nid'] !== NULL ? 'project #' . (int) $row['source_project_nid'] : '',
      trim((string) ($row['source_ref'] ?? '')),
    ]);
    return $parts === [] ? '—' : implode(' · ', $parts);
  }

  private function date(int $timestamp): string {
    return $timestamp > 0 ? $this->dateFormatter->format($timestamp, 'short') : '—';
  }

}
