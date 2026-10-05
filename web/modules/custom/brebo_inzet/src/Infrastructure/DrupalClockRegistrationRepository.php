<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\ClockRegistrationRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Drupal node adapter for clock-registration persistence. */
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

}
