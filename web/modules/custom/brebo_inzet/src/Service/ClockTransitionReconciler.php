<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Reconciles a previous early clock-out after a new project clock-in.
 */
final class ClockTransitionReconciler {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClockTransitionControl $transitionControl,
  ) {}

  public function reconcileAfterClockIn(NodeInterface $newRegistration, string $newGeoStatus, int $maxTransferMinutes = 90): ?NodeInterface {
    $userId = (int) ($newRegistration->get('field_brebo_clock_user')->target_id ?? 0);
    $toProjectId = (int) ($newRegistration->get('field_brebo_project_ref')->target_id ?? 0);
    $nextClockInValue = (string) $newRegistration->get('field_brebo_clock_in')->value;
    if ($userId <= 0 || $toProjectId <= 0 || $nextClockInValue === '') {
      return NULL;
    }

    $nextClockIn = new \DateTimeImmutable($nextClockInValue);
    $windowStart = $nextClockIn->modify(sprintf('-%d minutes', max(0, $maxTransferMinutes)));
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_clock_user', $userId)
      ->condition('field_brebo_clock_status', 'Vroeg uitgeklokt')
      ->condition('field_brebo_clock_out', $windowStart->format('Y-m-d\\TH:i:s'), '>=')
      ->condition('field_brebo_clock_out', $nextClockIn->format('Y-m-d\\TH:i:s'), '<=')
      ->sort('field_brebo_clock_out', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    $previous = $storage->load((int) reset($ids));
    if (!$previous instanceof NodeInterface) {
      return NULL;
    }

    $fromProjectId = (int) ($previous->get('field_brebo_project_ref')->target_id ?? 0);
    $clockOutValue = (string) $previous->get('field_brebo_clock_out')->value;
    if ($fromProjectId <= 0 || $clockOutValue === '') {
      return NULL;
    }

    $transition = $this->transitionControl->assess(
      $fromProjectId,
      new \DateTimeImmutable($clockOutValue),
      $toProjectId,
      $nextClockIn,
      $newGeoStatus,
      $maxTransferMinutes,
    );
    if ($transition['status'] !== 'Projectwissel bevestigd') {
      return NULL;
    }

    $previousMessage = json_decode((string) $previous->get('field_brebo_clock_message')->value, TRUE);
    if (!is_array($previousMessage)) {
      $previousMessage = [];
    }
    $previousMessage['transition'] = $transition;
    $previousMessage['status'] = 'Geldige projectwissel';
    $previousMessage['severity'] = 'groen';
    $previousMessage['requires_reason'] = FALSE;
    $previousMessage['message'] = $transition['message'];

    $previous->set('field_brebo_clock_status', 'Geldige projectwissel');
    $previous->set('field_brebo_clock_severity', 'groen');
    $previous->set('field_brebo_next_project_ref', ['target_id' => $toProjectId]);
    $previous->set('field_brebo_clock_message', json_encode($previousMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $previous->save();

    return $previous;
  }

}
