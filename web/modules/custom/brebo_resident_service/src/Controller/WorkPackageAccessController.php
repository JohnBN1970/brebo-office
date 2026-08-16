<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\brebo_resident_service\Service\WorkPackageAccessReadiness;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Work-package cockpit for access readiness and start-release evidence. */
final class WorkPackageAccessController extends ControllerBase {

  public function __construct(private readonly WorkPackageAccessReadiness $readiness) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('brebo_resident_service.work_package_access_readiness'));
  }

  public function title(NodeInterface $node): string {
    return 'Toegang & startgereed — ' . $node->label();
  }

  public function cockpit(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_work_package') {
      throw new NotFoundHttpException();
    }
    $evaluation = $this->readiness->evaluate($node);
    $summary = $evaluation['summary'];
    $rows = [];
    foreach ($summary['rows'] ?? [] as $row) {
      $ready = !empty($row['ready']);
      $rows[] = [
        $row['label'] ?? '—',
        $row['occupancy'] ?? '—',
        $row['status'] ?? '—',
        $row['inherited_from'] ?? '—',
        ['data' => ['#markup' => '<span class="brebo-status ' . ($ready ? 'brebo-status--positive' : 'brebo-status--attention') . '">' . ($ready ? 'Gereed' : 'Aandacht') . '</span>']],
      ];
    }

    $percentage = (float) ($summary['percentage'] ?? ($evaluation['ready'] ? 100 : 0));
    $attention = (int) ($summary['attention'] ?? 0);
    $statusClass = $evaluation['ready'] ? 'brebo-status--positive' : 'brebo-status--critical';
    $statusText = $evaluation['ready'] ? 'GEREED' : 'GEBLOKKEERD';

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-cockpit']],
      'header' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-cockpit__header']],
        'intro' => ['#markup' => '<div><span class="brebo-status ' . $statusClass . '">' . $statusText . '</span><p class="brebo-cockpit__intro">' . htmlspecialchars((string) $evaluation['reason'], ENT_QUOTES, 'UTF-8') . '</p></div>'],
        'actions' => [
          '#type' => 'container', '#attributes' => ['class' => ['brebo-actions']],
        ],
      ],
    ];

    if ($evaluation['technical_zone_id']) {
      $build['header']['actions']['access'] = [
        '#type' => 'link', '#title' => 'Toegangsafspraak vastleggen',
        '#url' => Url::fromRoute('brebo_resident_service.access_contact_add', ['scope_type' => 'technical_zone', 'scope_id' => $evaluation['technical_zone_id']]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    if ($evaluation['applicable']) {
      $build['kpis'] = [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-kpis']],
        'percentage' => ['#markup' => '<div class="brebo-kpi ' . ($evaluation['ready'] ? 'brebo-kpi--positive' : 'brebo-kpi--critical') . '"><span class="brebo-kpi__value">' . $percentage . '%</span><span class="brebo-kpi__label">Startgereed</span></div>'],
        'scope' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . (int) ($summary['total'] ?? 0) . '</span><span class="brebo-kpi__label">Scope-items</span></div>'],
        'ready' => ['#markup' => '<div class="brebo-kpi brebo-kpi--positive"><span class="brebo-kpi__value">' . (int) ($summary['ready'] ?? 0) . '</span><span class="brebo-kpi__label">Gereed</span></div>'],
        'attention' => ['#markup' => '<div class="brebo-kpi ' . ($attention > 0 ? 'brebo-kpi--attention' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $attention . '</span><span class="brebo-kpi__label">Aandacht</span></div>'],
      ];
      $build['exceptions'] = [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Uitzonderingen</h2></div>'],
        'items' => ['#theme' => 'item_list', '#items' => [
          'Leegstaand: ' . (int) ($summary['vacant'] ?? 0),
          'Geen contact: ' . (int) ($summary['no_contact'] ?? 0),
          'Geweigerd: ' . (int) ($summary['refused'] ?? 0),
          'Geblokkeerd: ' . (int) ($summary['blocked'] ?? 0),
        ]],
      ];
    }

    if ($rows) {
      $build['details'] = [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Technische scope & toegang</h2></div>'],
        'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']], 'table' => [
          '#type' => 'table', '#header' => ['Scope', 'Bewoning', 'Toegang', 'Herkomst regel', 'Startstatus'], '#rows' => $rows,
        ]],
      ];
    }

    $build['governance'] = [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
      'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Formele vrijgave</h2></div>'],
      'text' => ['#markup' => '<p style="padding:0 1rem 1rem">Deze automatische controle levert beslisinformatie voor de bestaande BREBO release-gate. Zij geeft nooit zelfstandig formeel akkoord.</p>'],
    ];
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }
}
