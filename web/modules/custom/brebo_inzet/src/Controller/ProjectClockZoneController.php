<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\ProjectClockZoneManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Project-bound clock zone administration.
 */
final class ProjectClockZoneController extends ControllerBase {

  public function __construct(
    private readonly ProjectClockZoneManager $clockZoneManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.project_clock_zone_manager'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Kloklocaties - ' . $node->label();
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $zones = $this->clockZoneManager->loadForProject($node);

    $rows = [];
    foreach ($zones as $zone) {
      $rows[] = [
        $zone['name'],
        number_format($zone['radius'], 0, ',', '.') . ' m',
        $zone['active'] ? $this->t('Actief') : $this->t('Inactief'),
        number_format($zone['latitude'], 6, ',', '.') . ', ' . number_format($zone['longitude'], 6, ',', '.'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Bewerken'),
                'url' => Url::fromRoute('entity.node.edit_form', ['node' => $zone['id']]),
              ],
              'delete' => [
                'title' => $this->t('Verwijderen'),
                'url' => Url::fromRoute('entity.node.delete_form', ['node' => $zone['id']]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-inzet-clock-zones']],
      'intro' => [
        '#markup' => '<p>' . $this->t('Leg één of meer toegestane kloklocaties vast. Het officiële gebouwadres blijft ongewijzigd; deze zones zijn uitsluitend bedoeld voor personeelsinzet en locatieklokken.') . '</p>',
      ],
      'actions' => [
        '#type' => 'actions',
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Kloklocatie toevoegen'),
          '#url' => Url::fromRoute('brebo_inzet.project_clock_zone_add', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Locatie'),
          $this->t('Radius'),
          $this->t('Status'),
          $this->t('Middelpunt'),
          $this->t('Acties'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen kloklocaties ingesteld voor dit project.'),
      ],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
