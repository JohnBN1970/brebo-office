<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\ClockRegistrationRepositoryInterface;

/** Opens and closes durable project clock registrations. */
final class ClockSessionManager {

  public function __construct(
    private readonly ClockRegistrationRepositoryInterface $registrationRepository,
    private readonly ProjectClockZoneManager $zoneManager,
    private readonly ProjectClockZoneControl $zoneControl,
    private readonly ClockActionControl $actionControl,
    private readonly ClockTransitionReconciler $transitionReconciler,
  ) {}

  /** @return array<string,mixed>|null */
  public function findOpen(int $projectId, int $userId): ?array {
    $open = $this->findOpenForUser($userId);
    return $open !== NULL && (int) $open['project_id'] === $projectId ? $open : NULL;
  }

  /** @return array<string,mixed>|null */
  public function findOpenForUser(int $userId): ?array {
    return $this->registrationRepository->openForUser($userId);
  }

  /** @return array<string,mixed> */
  public function clockIn(
    int $projectId,
    string $projectLabel,
    int $userId,
    ?float $latitude,
    ?float $longitude,
    ?float $accuracy,
  ): array {
    $existing = $this->findOpenForUser($userId);
    if ($existing !== NULL) {
      $label = trim((string) ($existing['project_label'] ?? '')) ?: ('project ' . (int) $existing['project_id']);
      throw new \InvalidArgumentException(sprintf('Je bent al ingeklokt op %s. Klok daar eerst uit voordat je op een ander project inklokt.', $label));
    }

    $geo = $this->zoneControl->assess(
      $this->zoneManager->loadForProject($projectId),
      $latitude,
      $longitude,
      $accuracy,
    );
    $now = new \DateTimeImmutable('now');
    $registrationId = $this->registrationRepository->create([
      'title' => sprintf('Klokregistratie %s - %s', $projectLabel, $now->format('Y-m-d H:i')),
      'project_id' => $projectId,
      'user_id' => $userId,
      'clock_zone_id' => !empty($geo['matched_zone_id']) ? (int) $geo['matched_zone_id'] : NULL,
      'clock_in' => $now->format('Y-m-d\\TH:i:s'),
      'clock_out' => NULL,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'accuracy' => $accuracy,
      'distance' => $geo['distance'] ?? NULL,
      'status' => 'Open',
      'severity' => ($geo['status'] ?? '') === 'Binnen zone' ? 'groen' : 'rood',
      'reason' => '',
      'next_project_id' => NULL,
      'message_json' => json_encode(['location' => $geo], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);

    $registration = $this->registrationRepository->registration($registrationId);
    $reconciled = $registration === NULL
      ? NULL
      : $this->transitionReconciler->reconcileAfterClockIn($registration, (string) ($geo['status'] ?? 'Geen locatie'));

    return [
      'registration' => $registration,
      'location' => $geo,
      'reconciled_registration' => $reconciled,
    ];
  }

  /**
   * @return array{0:string,1:string}
   */
  private function plannedTimesForUser(
    int $projectId,
    int $userId,
    string $date,
    string $defaultStart,
    string $defaultEnd,
  ): array {
    $assignment = $this->registrationRepository->plannedTimes($projectId, $userId, $date);
    return $assignment ?? [$defaultStart, $defaultEnd];
  }

  /** @return array<string,mixed> */
  public function clockOut(
    int $projectId,
    int $userId,
    string $defaultStart,
    string $defaultEnd,
    ?float $latitude,
    ?float $longitude,
    ?float $accuracy,
    ?string $reason = NULL,
  ): array {
    $registration = $this->findOpen($projectId, $userId);
    if ($registration === NULL) {
      $otherOpen = $this->findOpenForUser($userId);
      if ($otherOpen !== NULL) {
        throw new \InvalidArgumentException(sprintf(
          'Je actieve klokregistratie hoort bij project %d. Open dat project om uit te klokken.',
          (int) $otherOpen['project_id'],
        ));
      }
      throw new \InvalidArgumentException('Er is geen open klokregistratie om uit te klokken.');
    }

    $clockIn = new \DateTimeImmutable((string) $registration['clock_in']);
    $clockOut = new \DateTimeImmutable('now');
    $geo = $this->zoneControl->assess(
      $this->zoneManager->loadForProject($projectId),
      $latitude,
      $longitude,
      $accuracy,
    );

    $date = $clockIn->format('Y-m-d');
    [$startTime, $endTime] = $this->plannedTimesForUser(
      $projectId,
      $userId,
      $date,
      $defaultStart,
      $defaultEnd,
    );
    $plannedStart = new \DateTimeImmutable($date . ' ' . $startTime);
    $plannedEnd = new \DateTimeImmutable($date . ' ' . $endTime);

    $verdict = $this->actionControl->assess(
      $projectId,
      $plannedStart,
      $plannedEnd,
      $clockIn,
      $clockOut,
      $geo,
    );

    $normalizedReason = trim((string) $reason);
    if (!empty($verdict['requires_reason']) && $normalizedReason === '') {
      return [
        'registration' => $registration,
        'location' => $geo,
        'verdict' => $verdict,
        'requires_reason' => TRUE,
      ];
    }

    $this->registrationRepository->update((int) $registration['id'], [
      'clock_out' => $clockOut->format('Y-m-d\\TH:i:s'),
      'clock_zone_id' => !empty($geo['matched_zone_id']) ? (int) $geo['matched_zone_id'] : NULL,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'accuracy' => $accuracy,
      'distance' => $geo['distance'] ?? NULL,
      'status' => (string) $verdict['status'],
      'severity' => (string) $verdict['severity'],
      'reason' => $normalizedReason,
      'message_json' => json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);

    return [
      'registration' => $this->registrationRepository->registration((int) $registration['id']),
      'location' => $geo,
      'verdict' => $verdict,
      'requires_reason' => FALSE,
    ];
  }

}
