<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\ClockRegistrationRepositoryInterface;

/** Persists evaluated clock registrations as durable BREBO Office data. */
final class ClockRegistrationWriter {

  public function __construct(
    private readonly ClockRegistrationRepositoryInterface $registrationRepository,
  ) {}

  /**
   * @param array<string,mixed> $verdict
   */
  public function save(
    int $projectId,
    string $projectLabel,
    int $userId,
    ?int $clockZoneId,
    ?\DateTimeImmutable $clockIn,
    ?\DateTimeImmutable $clockOut,
    ?float $latitude,
    ?float $longitude,
    ?float $accuracy,
    ?float $distance,
    array $verdict,
    ?string $reason = NULL,
    ?int $nextProjectId = NULL,
  ): int {
    if ($projectId <= 0) {
      throw new \InvalidArgumentException('Clock registrations must belong to a valid BREBO project.');
    }
    if ($userId <= 0) {
      throw new \InvalidArgumentException('Clock registrations must belong to a valid Office user.');
    }

    $status = (string) ($verdict['status'] ?? 'Onbekend');
    $severity = (string) ($verdict['severity'] ?? 'rood');
    $requiresReason = (bool) ($verdict['requires_reason'] ?? TRUE);
    $normalizedReason = trim((string) $reason);

    if ($requiresReason && $normalizedReason === '') {
      throw new \InvalidArgumentException('Een reden is verplicht voor deze klokafwijking.');
    }

    return $this->registrationRepository->create([
      'title' => sprintf('Klokregistratie %s - %s', $projectLabel, $clockIn?->format('Y-m-d H:i') ?? 'onbekend'),
      'project_id' => $projectId,
      'user_id' => $userId,
      'clock_zone_id' => $clockZoneId,
      'clock_in' => $clockIn?->format('Y-m-d\\TH:i:s'),
      'clock_out' => $clockOut?->format('Y-m-d\\TH:i:s'),
      'latitude' => $latitude,
      'longitude' => $longitude,
      'accuracy' => $accuracy,
      'distance' => $distance,
      'status' => $status,
      'severity' => $severity,
      'reason' => $normalizedReason,
      'next_project_id' => $nextProjectId,
      'message_json' => json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
  }

}
