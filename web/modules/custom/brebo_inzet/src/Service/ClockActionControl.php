<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Produces one operational verdict for a completed clock registration.
 */
final class ClockActionControl {

  public function __construct(
    private readonly ClockTimeControl $timeControl,
    private readonly ClockTransitionControl $transitionControl,
  ) {}

  /**
   * @param array<string, mixed> $geoAssessment
   *
   * @return array{status: string, severity: string, requires_reason: bool, time: array<string, mixed>, location: array<string, mixed>, transition: ?array<string, mixed>, message: string}
   */
  public function assess(
    int $projectId,
    \DateTimeImmutable $plannedStart,
    \DateTimeImmutable $plannedEnd,
    ?\DateTimeImmutable $clockIn,
    ?\DateTimeImmutable $clockOut,
    array $geoAssessment,
    ?int $nextProjectId = NULL,
    ?\DateTimeImmutable $nextClockIn = NULL,
    ?string $nextGeoStatus = NULL,
    int $timeToleranceMinutes = 5,
    int $maxTransferMinutes = 90,
  ): array {
    $time = $this->timeControl->assess(
      $plannedStart,
      $plannedEnd,
      $clockIn,
      $clockOut,
      $timeToleranceMinutes,
    );

    $geoStatus = (string) ($geoAssessment['status'] ?? 'Geen locatie');
    $locationOk = $geoStatus === 'Binnen zone';

    if ($time['status'] === 'Onvolledig') {
      return $this->verdict('Onvolledige klokregistratie', 'rood', TRUE, $time, $geoAssessment, NULL, $time['message']);
    }

    if (!$locationOk) {
      $message = $geoStatus === 'Buiten zone'
        ? 'Klokactie ligt buiten alle toegestane projectzones.'
        : 'Locatie van de klokactie kon niet geldig worden bevestigd.';
      return $this->verdict('Locatie-afwijking', 'rood', TRUE, $time, $geoAssessment, NULL, $message);
    }

    if ($time['status'] === 'Te vroeg uitgeklokt' && $clockOut !== NULL) {
      $transition = $this->transitionControl->assess(
        $projectId,
        $clockOut,
        $nextProjectId,
        $nextClockIn,
        $nextGeoStatus,
        $maxTransferMinutes,
      );

      if ($transition['status'] === 'Projectwissel bevestigd') {
        return $this->verdict(
          'Geldige projectwissel',
          'groen',
          FALSE,
          $time,
          $geoAssessment,
          $transition,
          $transition['message'],
        );
      }

      return $this->verdict(
        'Vroeg uitgeklokt',
        'oranje',
        TRUE,
        $time,
        $geoAssessment,
        $transition,
        $time['message'] . ' ' . $transition['message'],
      );
    }

    if ($time['status'] === 'Te laat uitgeklokt') {
      return $this->verdict('Laat uitgeklokt', 'oranje', TRUE, $time, $geoAssessment, NULL, $time['message']);
    }

    return $this->verdict('Volgens planning', 'groen', FALSE, $time, $geoAssessment, NULL, 'Kloktijd en locatie zijn akkoord.');
  }

  /**
   * @param array<string, mixed> $time
   * @param array<string, mixed> $location
   * @param array<string, mixed>|null $transition
   *
   * @return array{status: string, severity: string, requires_reason: bool, time: array<string, mixed>, location: array<string, mixed>, transition: ?array<string, mixed>, message: string}
   */
  private function verdict(string $status, string $severity, bool $requiresReason, array $time, array $location, ?array $transition, string $message): array {
    return [
      'status' => $status,
      'severity' => $severity,
      'requires_reason' => $requiresReason,
      'time' => $time,
      'location' => $location,
      'transition' => $transition,
      'message' => $message,
    ];
  }

}
