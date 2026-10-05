<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\ClockRegistrationRepositoryInterface;

/** Reconciles a previous early clock-out after a new project clock-in. */
final class ClockTransitionReconciler {

  public function __construct(
    private readonly ClockRegistrationRepositoryInterface $registrationRepository,
    private readonly ClockTransitionControl $transitionControl,
  ) {}

  /**
   * @param array{id:int,project_id:int,user_id:int,clock_in:string,clock_out:?string,message_json:string} $newRegistration
   * @return array<string,mixed>|null
   */
  public function reconcileAfterClockIn(
    array $newRegistration,
    string $newGeoStatus,
    int $maxTransferMinutes = 90,
  ): ?array {
    $userId = (int) $newRegistration['user_id'];
    $toProjectId = (int) $newRegistration['project_id'];
    $nextClockInValue = (string) $newRegistration['clock_in'];
    if ($userId <= 0 || $toProjectId <= 0 || $nextClockInValue === '') {
      return NULL;
    }

    $nextClockIn = new \DateTimeImmutable($nextClockInValue);
    $windowStart = $nextClockIn->modify(sprintf('-%d minutes', max(0, $maxTransferMinutes)));
    $previous = $this->registrationRepository->latestEarlyClockOut(
      $userId,
      $windowStart->format('Y-m-d\\TH:i:s'),
      $nextClockIn->format('Y-m-d\\TH:i:s'),
    );
    if ($previous === NULL) {
      return NULL;
    }

    $fromProjectId = (int) $previous['project_id'];
    $clockOutValue = (string) ($previous['clock_out'] ?? '');
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

    $previousMessage = json_decode((string) $previous['message_json'], TRUE);
    if (!is_array($previousMessage)) {
      $previousMessage = [];
    }
    $previousMessage['transition'] = $transition;
    $previousMessage['status'] = 'Geldige projectwissel';
    $previousMessage['severity'] = 'groen';
    $previousMessage['requires_reason'] = FALSE;
    $previousMessage['message'] = $transition['message'];

    $this->registrationRepository->update((int) $previous['id'], [
      'status' => 'Geldige projectwissel',
      'severity' => 'groen',
      'next_project_id' => $toProjectId,
      'message_json' => json_encode($previousMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);

    return $this->registrationRepository->registration((int) $previous['id']);
  }

}
