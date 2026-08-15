<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\brebo_resident_service\Service\AccessContactResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectAccessController extends ControllerBase {

  public function __construct(private readonly Connection $database, private readonly AccessContactResolver $resolver) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_resident_service.access_contact_resolver'));
  }

  public function title(NodeInterface $node): string {
    return $this->t('Toegang & aanspreekpunten — @project', ['@project' => $node->label()]);
  }

  public function cockpit(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $projectId = (int) $node->id();
    $projectAccess = $this->resolver->resolve(NULL, NULL, NULL, $projectId);
    $counts = ['ready' => 0, 'to_arrange' => 0, 'no_contact' => 0, 'refused' => 0, 'blocked' => 0, 'unknown' => 0, 'not_needed' => 0];
    $occupancy = ['occupied' => 0, 'vacant' => 0, 'temporarily_vacant' => 0, 'unknown' => 0];
    $vacantReady = 0;
    $vacantAttention = 0;
    $buildingCounts = [];
    $rows = [];

    $query = $this->database->select('brebo_residence', 'r')->fields('r', ['id', 'building_nid', 'address_line', 'project_id', 'occupancy_status']);
    $query->condition('r.project_id', $projectId)->orderBy('r.building_nid')->orderBy('r.street')->orderBy('r.house_number')->orderBy('r.addition');
    foreach ($query->execute() as $residence) {
      $occupancyStatus = (string) ($residence->occupancy_status ?: 'unknown');
      if (!array_key_exists($occupancyStatus, $occupancy)) {
        $occupancyStatus = 'unknown';
      }
      $occupancy[$occupancyStatus]++;
      $effective = $this->resolver->resolve((int) $residence->building_nid, NULL, (int) $residence->id, $projectId);
      $status = $effective['access_status'] ?? 'unknown';
      $ready = $effective ? $this->resolver->isReady($effective) : FALSE;
      $bucket = $ready ? 'ready' : (array_key_exists($status, $counts) ? $status : 'unknown');
      $counts[$bucket]++;
      if (in_array($occupancyStatus, ['vacant', 'temporarily_vacant'], TRUE)) {
        $ready ? $vacantReady++ : $vacantAttention++;
      }
      $buildingId = (int) $residence->building_nid;
      $buildingCounts[$buildingId] ??= ['total' => 0, 'ready' => 0, 'attention' => 0, 'vacant' => 0];
      $buildingCounts[$buildingId]['total']++;
      $buildingCounts[$buildingId][$ready ? 'ready' : 'attention']++;
      if (in_array($occupancyStatus, ['vacant', 'temporarily_vacant'], TRUE)) {
        $buildingCounts[$buildingId]['vacant']++;
      }
      $address = Link::fromTextAndUrl((string) $residence->address_line, Url::fromRoute('brebo_resident_service.residence_detail', ['residence_id' => (int) $residence->id]))->toRenderable();
      $rows[] = [
        ['data' => $address], $buildingId, ucfirst(str_replace('_', ' ', $occupancyStatus)),
        $effective['contact_name'] ?? '—', $effective['contact_role'] ?? '—', $effective['access_via'] ?? '—',
        ucfirst(str_replace('_', ' ', $status)), $effective['inherited_from'] ?? '—',
        ['data' => ['#markup' => '<span class="brebo-status ' . ($ready ? 'brebo-status--positive' : 'brebo-status--attention') . '">' . ($ready ? $this->t('Gereed') : $this->t('Aandacht')) . '</span>']],
      ];
    }

    $buildingRows = [];
    foreach ($buildingCounts as $buildingId => $stats) {
      $label = $this->database->select('node_field_data', 'n')->fields('n', ['title'])->condition('nid', $buildingId)->execute()->fetchField() ?: ('#' . $buildingId);
      $buildingRows[] = [Link::fromTextAndUrl((string) $label, Url::fromRoute('brebo_resident_service.building_residents', ['node' => $buildingId]))->toRenderable(), $stats['total'], $stats['vacant'], $stats['ready'], $stats['attention']];
    }

    $total = array_sum($counts);
    $attention = $total - $counts['ready'] - $counts['not_needed'];
    $vacant = $occupancy['vacant'] + $occupancy['temporarily_vacant'];
    $percentage = $total > 0 ? round(($counts['ready'] / $total) * 100, 1) : 100;
    $contactLabel = $projectAccess
      ? trim(($projectAccess['contact_name'] ?: '—') . ' · ' . ($projectAccess['contact_role'] ?: '—'), ' ·')
      : (string) $this->t('Niet vastgelegd');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-cockpit']],
      'header' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-cockpit__header']],
        'intro' => ['#markup' => '<p class="brebo-cockpit__intro">' . $this->t('Operationeel overzicht van toegang en aanspreekpunten. Bewoning en toegang worden afzonderlijk beoordeeld; leegstand betekent niet automatisch vrije toegang.') . '</p>'],
        'actions' => [
          '#type' => 'container', '#attributes' => ['class' => ['brebo-actions']],
          'add' => Link::fromTextAndUrl($this->t('Toegangsregel toevoegen'), Url::fromRoute('brebo_resident_service.access_contact_add', ['scope_type' => 'project', 'scope_id' => $projectId]))->toRenderable(),
        ],
      ],
      'kpis' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-kpis']],
        'ready' => ['#markup' => '<div class="brebo-kpi brebo-kpi--positive"><span class="brebo-kpi__value">' . $percentage . '%</span><span class="brebo-kpi__label">Startgereed</span></div>'],
        'attention' => ['#markup' => '<div class="brebo-kpi ' . ($attention > 0 ? 'brebo-kpi--attention' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $attention . '</span><span class="brebo-kpi__label">Aandacht toegang</span></div>'],
        'vacant' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . $vacant . '</span><span class="brebo-kpi__label">Leegstaand · ' . $vacantReady . ' gereed / ' . $vacantAttention . ' aandacht</span></div>'],
        'contact' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value" style="font-size:1rem">' . htmlspecialchars($contactLabel, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-kpi__label">Projectaanspreekpunt</span></div>'],
      ],
      'exceptions' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">' . $this->t('Uitzonderingen') . '</h2></div>'],
        'items' => ['#theme' => 'item_list', '#items' => [
          $this->t('Nog regelen: @n', ['@n' => $counts['to_arrange']]),
          $this->t('Geen contact: @n', ['@n' => $counts['no_contact']]),
          $this->t('Geweigerd: @n', ['@n' => $counts['refused']]),
          $this->t('Geblokkeerd: @n', ['@n' => $counts['blocked']]),
          $this->t('Onbekend: @n', ['@n' => $counts['unknown']]),
        ]],
      ],
      'buildings' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">' . $this->t('Per gebouw') . '</h2></div>'],
        'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']], 'table' => ['#type' => 'table', '#header' => ['Gebouw', 'In scope', 'Leegstaand', 'Gereed', 'Aandacht'], '#rows' => $buildingRows, '#empty' => $this->t('Geen woningen aan dit project gekoppeld.')]],
      ],
      'residences' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">' . $this->t('Toegang per woning') . '</h2></div>'],
        'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']], 'table' => ['#type' => 'table', '#header' => ['Adres', 'Gebouw', 'Bewoning', 'Aanspreekpunt', 'Rol', 'Toegang via', 'Status', 'Bron regel', 'Startgereed'], '#rows' => $rows, '#empty' => $this->t('Geen woningen aan dit project gekoppeld.')]],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
