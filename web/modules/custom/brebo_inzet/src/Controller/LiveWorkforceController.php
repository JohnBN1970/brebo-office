<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows who is currently clocked in on a project.
 */
final class LiveWorkforceController extends ControllerBase {

  public function title(NodeInterface $node): string {
    return (string) $this->t('Nu aan het werk - @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', (int) $node->id())
      ->condition('field_brebo_clock_status', 'Open')
      ->sort('field_brebo_clock_in', 'ASC')
      ->execute();
    $registrations = $ids ? $storage->loadMultiple($ids) : [];
    $now = new \DateTimeImmutable('now');
    $rows = [];

    foreach ($registrations as $registration) {
      if (!$registration instanceof NodeInterface) {
        continue;
      }
      $account = $registration->get('field_brebo_clock_user')->entity;
      $zone = $registration->get('field_brebo_clock_zone_ref')->entity;
      $clockInValue = (string) $registration->get('field_brebo_clock_in')->value;
      $clockIn = $clockInValue !== '' ? new \DateTimeImmutable($clockInValue) : NULL;
      $seconds = $clockIn ? max(0, $now->getTimestamp() - $clockIn->getTimestamp()) : 0;
      $hours = intdiv($seconds, 3600);
      $minutes = intdiv($seconds % 3600, 60);
      $locationPayload = json_decode((string) $registration->get('field_brebo_clock_message')->value, TRUE);
      $locationStatus = is_array($locationPayload) ? (string) ($locationPayload['location']['status'] ?? 'Onbekend') : 'Onbekend';

      $rows[] = [
        'data' => [
          $account ? $account->label() : $this->t('Onbekende medewerker'),
          $clockIn ? $clockIn->format('H:i') : '-',
          sprintf('%d:%02d', $hours, $minutes),
          $zone ? $zone->label() : $this->t('Geen zone herkend'),
          $locationStatus,
        ],
      ];
    }

    return [
      'summary' => [
        '#markup' => '<p><strong>' . $this->formatPlural(count($rows), '1 persoon actief', '@count personen actief') . '</strong></p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Medewerker'),
          $this->t('Sinds'),
          $this->t('Actief'),
          $this->t('Kloklocatie'),
          $this->t('Locatiecontrole'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er is momenteel niemand ingeklokt op dit project.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
