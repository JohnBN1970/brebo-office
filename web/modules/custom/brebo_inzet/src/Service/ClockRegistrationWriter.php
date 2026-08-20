<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Persists evaluated clock registrations as durable BREBO Office data.
 */
final class ClockRegistrationWriter {

  public function __construct(
    private readonly EntityTypeManagerInterface $clockRegistrationEntityTypeManager,
  ) {}

  /**
   * @param array<string, mixed> $verdict
   */
  public function save(
    NodeInterface $project,
    int $userId,
    ?NodeInterface $clockZone,
    ?\DateTimeImmutable $clockIn,
    ?\DateTimeImmutable $clockOut,
    ?float $latitude,
    ?float $longitude,
    ?float $accuracy,
    ?float $distance,
    array $verdict,
    ?string $reason = NULL,
    ?NodeInterface $nextProject = NULL,
  ): NodeInterface {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Clock registrations must belong to a BREBO project.');
    }
    if ($clockZone !== NULL && $clockZone->bundle() !== 'brebo_clock_zone') {
      throw new \InvalidArgumentException('Clock zone reference must be a BREBO clock zone.');
    }
    if ($nextProject !== NULL && $nextProject->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Next project reference must be a BREBO project.');
    }

    $status = (string) ($verdict['status'] ?? 'Onbekend');
    $severity = (string) ($verdict['severity'] ?? 'rood');
    $requiresReason = (bool) ($verdict['requires_reason'] ?? TRUE);
    $normalizedReason = trim((string) $reason);

    if ($requiresReason && $normalizedReason === '') {
      throw new \InvalidArgumentException('Een reden is verplicht voor deze klokafwijking.');
    }

    $storage = $this->clockRegistrationEntityTypeManager->getStorage('node');
    $registration = $storage->create([
      'type' => 'brebo_clock_registration',
      'title' => sprintf('Klokregistratie %s - %s', $project->label(), $clockIn?->format('Y-m-d H:i') ?? 'onbekend'),
      'field_brebo_project_ref' => ['target_id' => (int) $project->id()],
      'field_brebo_user_ref' => ['target_id' => $userId],
      'field_brebo_clock_zone_ref' => $clockZone ? ['target_id' => (int) $clockZone->id()] : NULL,
      'field_brebo_clock_in' => $clockIn?->format(DATE_ATOM),
      'field_brebo_clock_out' => $clockOut?->format(DATE_ATOM),
      'field_brebo_clock_latitude' => $latitude,
      'field_brebo_clock_longitude' => $longitude,
      'field_brebo_clock_accuracy' => $accuracy,
      'field_brebo_clock_distance' => $distance,
      'field_brebo_clock_status' => $status,
      'field_brebo_clock_severity' => $severity,
      'field_brebo_clock_reason' => $normalizedReason,
      'field_brebo_next_project_ref' => $nextProject ? ['target_id' => (int) $nextProject->id()] : NULL,
      'field_brebo_clock_assessment' => json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      'status' => 1,
    ]);
    $registration->save();

    return $registration;
  }

}
