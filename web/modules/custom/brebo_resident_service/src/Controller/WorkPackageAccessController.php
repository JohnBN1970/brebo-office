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
      $rows[] = [
        $row['label'] ?? '-',
        $row['occupancy'] ?? '-',
        $row['status'] ?? '-',
        $row['inherited_from'] ?? '-',
        !empty($row['ready']) ? 'Gereed' : 'Aandacht',
      ];
    }

    $build = [
      '#type' => 'container',
      'status' => [
        '#type' => 'item',
        '#title' => 'Startvrijgave — toegang',
        '#markup' => '<strong>' . ($evaluation['ready'] ? 'GEREED' : 'GEBLOKKEERD') . '</strong><br>' . $evaluation['reason'],
      ],
    ];
    if ($evaluation['applicable']) {
      $build['metrics'] = [
        '#theme' => 'item_list',
        '#title' => 'Readiness',
        '#items' => [
          'Startgereed: ' . ($summary['percentage'] ?? 0) . '%',
          'Scope-items: ' . ($summary['total'] ?? 0),
          'Gereed: ' . ($summary['ready'] ?? 0),
          'Aandacht: ' . ($summary['attention'] ?? 0),
          'Leegstaand: ' . ($summary['vacant'] ?? 0),
          'Geen contact: ' . ($summary['no_contact'] ?? 0),
          'Geweigerd: ' . ($summary['refused'] ?? 0),
          'Geblokkeerd: ' . ($summary['blocked'] ?? 0),
        ],
      ];
    }
    if ($rows) {
      $build['details'] = [
        '#type' => 'table',
        '#header' => ['Scope', 'Bewoning', 'Toegang', 'Herkomst regel', 'Startstatus'],
        '#rows' => $rows,
        '#empty' => 'Geen woninggebonden toegangsregels in deze technische scope.',
      ];
    }
    if ($evaluation['technical_zone_id']) {
      $build['access'] = [
        '#type' => 'link',
        '#title' => 'Toegangsafspraak voor technische zone vastleggen',
        '#url' => Url::fromRoute('brebo_resident_service.access_contact_add', [
          'scope_type' => 'technical_zone', 'scope_id' => $evaluation['technical_zone_id'],
        ]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }
    $build['note'] = [
      '#type' => 'item',
      '#title' => 'Besturingsregel',
      '#markup' => 'Deze controle levert het toegangsbewijs voor startvrijgave. De bestaande BREBO release-gate blijft het formele vrijgaveobject; deze module introduceert geen tweede vrijgavesysteem.',
    ];
    return $build;
  }
}
