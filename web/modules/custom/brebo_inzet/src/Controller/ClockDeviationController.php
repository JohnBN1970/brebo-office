<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists actionable clock deviations for a project.
 */
final class ClockDeviationController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Inzetafwijkingen - ' . $node->label();
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', (int) $node->id())
      ->condition('field_brebo_clock_severity', ['oranje', 'rood'], 'IN')
      ->sort('created', 'DESC')
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $registration) {
      $user = $registration->get('field_brebo_user_ref')->entity;
      $zone = $registration->get('field_brebo_clock_zone_ref')->entity;
      $rows[] = [
        $registration->get('field_brebo_clock_in')->value ?: '-',
        $user?->label() ?? '-',
        $registration->get('field_brebo_clock_status')->value ?: '-',
        strtoupper((string) ($registration->get('field_brebo_clock_severity')->value ?: '-')),
        $zone?->label() ?? '-',
        $registration->get('field_brebo_clock_reason')->value ?: $this->t('Nog geen reden vastgelegd'),
      ];
    }

    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('Alleen klokregistraties waarop iemand moet handelen staan hier. Groene registraties blijven uit beeld.') . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Ingeklokt'),
          $this->t('Medewerker'),
          $this->t('Afwijking'),
          $this->t('Ernst'),
          $this->t('Kloklocatie'),
          $this->t('Reden'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Geen open klokafwijkingen voor dit project.'),
      ],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
