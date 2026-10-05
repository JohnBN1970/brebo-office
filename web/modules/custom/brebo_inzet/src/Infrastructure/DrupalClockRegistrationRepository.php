<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\ClockRegistrationRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for clock-registration persistence and session reads. */
final class DrupalClockRegistrationRepository implements ClockRegistrationRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function create(array $fields): int {
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_clock_registration',
      'title' => (string) $fields['title'],
      'field_brebo_project_ref' => ['target_id' => (int) $fields['project_id']],
      'field_brebo_clock_user' => ['target_id' => (int) $fields['user_id']],
      'field_brebo_clock_zone_ref' => !empty($fields['clock_zone_id']) ? ['target_id' => (int) $fields['clock_zone_id']] : NULL,
      'field_brebo_clock_in' => $fields['clock_in'],
      'field_brebo_clock_out' => $fields['clock_out'],
      'field_brebo_clock_latitude' => $fields['latitude'],
      'field_brebo_clock_longitude' => $fields['longitude'],
      'field_brebo_clock_accuracy' => $fields['accuracy'],
      'field_brebo_clock_distance' => $fields['distance'],
      'field_brebo_clock_status' => (string) $fields['status'],
      'field_brebo_clock_severity' => (string) $fields['severity'],
      'field_brebo_clock_reason' => (string) $fields['reason'],
      'field_brebo_next_project_ref' => !empty($fields['next_project_id']) ? ['target_id' => (int) $fields['next_project_id']] : NULL,
      'field_brebo_clock_message' => (string) $fields['message_json'],
      'status' => 1,
    ]);
    $registration->save();
    return (int) $registration->id();
  }

  public function openForUser(int $userId): ?array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
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

    $registration = $storage->load((int) reset($ids));
    if (!$registration instanceof NodeInterface) {
      return NULL;
    }

    $projectId = (int) ($registration->get('field_brebo_project_ref')->target_id ?? 0);
    $project = $projectId > 0 ? $storage->load($projectId) : NULL;

    return [
      'id' => (int) $registration->id(),
      'project_id' => $projectId,
      'project_label' => $project instanceof NodeInterface ? (string) $project->label() : ('project ' . $projectId),
      'user_id' => (int) ($registration->get('field_brebo_clock_user')->target_id ?? 0),
      'clock_in' => (string) ($registration->get('field_brebo_clock_in')->value ?? ''),
      'status' => (string) ($registration->get('field_brebo_clock_status')->value ?? ''),
    ];
  }

  public function registration(int $registrationId): ?array {
    $registration = $this->entityTypeManager->getStorage('node')->load($registrationId);
    if (!$registration instanceof NodeInterface || $registration->bundle() !== 'brebo_clock_registration') {
      return NULL;
    }

    return [
      'id' => (int) $registration->id(),
      'project_id' => (int) ($registration->get('field_brebo_project_ref')->target_id ?? 0),
      'user_id' => (int) ($registration->get('field_brebo_clock_user')->target_id ?? 0),
      'clock_in' => (string) ($registration->get('field_brebo_clock_in')->value ?? ''),
      'clock_out' => ($value = (string) ($registration->get('field_brebo_clock_out')->value ?? '')) !== '' ? $value : NULL,
      'message_json' => (string) ($registration->get('field_brebo_clock_message')->value ?? ''),
    ];
  }

  public function plannedTimes(int $projectId, int $userId, string $date): ?array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_plan_user', $userId)
      ->condition('field_brebo_plan_date', $date)
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    $assignment = $storage->load((int) reset($ids));
    if (!$assignment instanceof NodeInterface) {
      return NULL;
    }

    $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
    $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
    if (preg_match('/^\d{2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{2}:\d{2}$/', $end) !== 1 || $end <= $start) {
      return NULL;
    }

    return [$start, $end];
  }

  public function latestEarlyClockOut(int $userId, string $windowStart, string $windowEnd): ?array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_clock_user', $userId)
      ->condition('field_brebo_clock_status', 'Vroeg uitgeklokt')
      ->condition('field_brebo_clock_out', $windowStart, '>=')
      ->condition('field_brebo_clock_out', $windowEnd, '<=')
      ->sort('field_brebo_clock_out', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    return $this->registration((int) reset($ids));
  }

  public function update(int $registrationId, array $fields): void {
    $registration = $this->entityTypeManager->getStorage('node')->load($registrationId);
    if (!$registration instanceof NodeInterface || $registration->bundle() !== 'brebo_clock_registration') {
      throw new \RuntimeException('Klokregistratie niet gevonden.');
    }

    $map = [
      'clock_zone_id' => 'field_brebo_clock_zone_ref',
      'clock_out' => 'field_brebo_clock_out',
      'latitude' => 'field_brebo_clock_latitude',
      'longitude' => 'field_brebo_clock_longitude',
      'accuracy' => 'field_brebo_clock_accuracy',
      'distance' => 'field_brebo_clock_distance',
      'status' => 'field_brebo_clock_status',
      'severity' => 'field_brebo_clock_severity',
      'reason' => 'field_brebo_clock_reason',
      'next_project_id' => 'field_brebo_next_project_ref',
      'message_json' => 'field_brebo_clock_message',
    ];

    foreach ($fields as $key => $value) {
      if (!isset($map[$key])) {
        continue;
      }
      if ($key === 'clock_zone_id' || $key === 'next_project_id') {
        $registration->set($map[$key], $value ? ['target_id' => (int) $value] : NULL);
      }
      else {
        $registration->set($map[$key], $value);
      }
    }
    $registration->save();
  }

}
