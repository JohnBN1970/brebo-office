<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Opens and closes durable project clock registrations.
 */
final class ClockSessionManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectClockZoneManager $zoneManager,
    private readonly ProjectClockZoneControl $zoneControl,
    private readonly ClockActionControl $actionControl,
    private readonly ClockTransitionReconciler $transitionReconciler,
  ) {}

  public function findOpen(NodeInterface $project, int $userId): ?NodeInterface {
    $open = $this->findOpenForUser($userId);
    if (!$open instanceof NodeInterface) {
      return NULL;
    }
    return (int) ($open->get('field_brebo_project_ref')->target_id ?? 0) === (int) $project->id() ? $open : NULL;
  }

  public function findOpenForUser(int $userId): ?NodeInterface {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_clock_user', $userId)
      ->condition('field_brebo_clock_status', 'Open')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $registration = $this->entityTypeManager->getStorage('node')->load((int) reset($ids));
    return $registration instanceof NodeInterface ? $registration : NULL;
  }

  /** @return array<string, mixed> */
  public function clockIn(NodeInterface $project, int $userId, ?float $latitude, ?float $longitude, ?float $accuracy): array {
    $existing = $this->findOpenForUser($userId);
    if ($existing instanceof NodeInterface) {
      $existingProjectId = (int) ($existing->get('field_brebo_project_ref')->target_id ?? 0);
      $existingProject = $existingProjectId > 0 ? $this->entityTypeManager->getStorage('node')->load($existingProjectId) : NULL;
      $label = $existingProject instanceof NodeInterface ? $existingProject->label() : ('project ' . $existingProjectId);
      throw new \InvalidArgumentException(sprintf('Je bent al ingeklokt op %s. Klok daar eerst uit voordat je op een ander project inklokt.', $label));
    }

    $geo = $this->zoneControl->assess($this->zoneManager->loadForProject($project), $latitude, $longitude, $accuracy);
    $now = new \DateTimeImmutable('now');
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_clock_registration',
      'title' => sprintf('Klokregistratie %s - %s', $project->label(), $now->format('Y-m-d H:i')),
      'field_brebo_project_ref' => ['target_id' => (int) $project->id()],
      'field_brebo_clock_user' => ['target_id' => $userId],
      'field_brebo_clock_zone_ref' => !empty($geo['matched_zone_id']) ? ['target_id' => (int) $geo['matched_zone_id']] : NULL,
      'field_brebo_clock_in' => $now->format('Y-m-d\\TH:i:s'),
      'field_brebo_clock_latitude' => $latitude,
      'field_brebo_clock_longitude' => $longitude,
      'field_brebo_clock_accuracy' => $accuracy,
      'field_brebo_clock_distance' => $geo['distance'] ?? NULL,
      'field_brebo_clock_status' => 'Open',
      'field_brebo_clock_severity' => ($geo['status'] ?? '') === 'Binnen zone' ? 'groen' : 'rood',
      'field_brebo_clock_message' => json_encode(['location' => $geo], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      'status' => 1,
    ]);
    $registration->save();

    $reconciled = $this->transitionReconciler->reconcileAfterClockIn($registration, (string) ($geo['status'] ?? 'Geen locatie'));

    return ['registration' => $registration, 'location' => $geo, 'reconciled_registration' => $reconciled];
  }

  /** @return array<string, mixed> */
  public function clockOut(NodeInterface $project, int $userId, ?float $latitude, ?float $longitude, ?float $accuracy, ?string $reason = NULL): array {
    $registration = $this->findOpen($project, $userId);
    if ($registration === NULL) {
      $otherOpen = $this->findOpenForUser($userId);
      if ($otherOpen instanceof NodeInterface) {
        $otherProjectId = (int) ($otherOpen->get('field_brebo_project_ref')->target_id ?? 0);
        throw new \InvalidArgumentException(sprintf('Je actieve klokregistratie hoort bij project %d. Open dat project om uit te klokken.', $otherProjectId));
      }
      throw new \InvalidArgumentException('Er is geen open klokregistratie om uit te klokken.');
    }

    $clockInValue = (string) $registration->get('field_brebo_clock_in')->value;
    $clockIn = new \DateTimeImmutable($clockInValue);
    $clockOut = new \DateTimeImmutable('now');
    $geo = $this->zoneControl->assess($this->zoneManager->loadForProject($project), $latitude, $longitude, $accuracy);

    $date = $clockIn->format('Y-m-d');
    $startTime = $project->hasField('field_brebo_workday_start') && $project->get('field_brebo_workday_start')->value
      ? (string) $project->get('field_brebo_workday_start')->value : '07:00';
    $endTime = $project->hasField('field_brebo_workday_end') && $project->get('field_brebo_workday_end')->value
      ? (string) $project->get('field_brebo_workday_end')->value : '16:00';
    $plannedStart = new \DateTimeImmutable($date . ' ' . $startTime);
    $plannedEnd = new \DateTimeImmutable($date . ' ' . $endTime);

    $verdict = $this->actionControl->assess(
      (int) $project->id(),
      $plannedStart,
      $plannedEnd,
      $clockIn,
      $clockOut,
      $geo,
    );

    $normalizedReason = trim((string) $reason);
    if (!empty($verdict['requires_reason']) && $normalizedReason === '') {
      return ['registration' => $registration, 'location' => $geo, 'verdict' => $verdict, 'requires_reason' => TRUE];
    }

    $registration->set('field_brebo_clock_out', $clockOut->format('Y-m-d\\TH:i:s'));
    $registration->set('field_brebo_clock_zone_ref', !empty($geo['matched_zone_id']) ? ['target_id' => (int) $geo['matched_zone_id']] : NULL);
    $registration->set('field_brebo_clock_latitude', $latitude);
    $registration->set('field_brebo_clock_longitude', $longitude);
    $registration->set('field_brebo_clock_accuracy', $accuracy);
    $registration->set('field_brebo_clock_distance', $geo['distance'] ?? NULL);
    $registration->set('field_brebo_clock_status', (string) $verdict['status']);
    $registration->set('field_brebo_clock_severity', (string) $verdict['severity']);
    $registration->set('field_brebo_clock_reason', $normalizedReason);
    $registration->set('field_brebo_clock_message', json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $registration->save();

    return ['registration' => $registration, 'location' => $geo, 'verdict' => $verdict, 'requires_reason' => FALSE];
  }

}
