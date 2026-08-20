<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Compares planned shift times with actual clock events.
 */
final class ClockTimeControl {

  /**
   * @return array{status: string, early_minutes: int, late_minutes: int, message: string}
   */
  public function assess(
    \DateTimeImmutable $plannedStart,
    \DateTimeImmutable $plannedEnd,
    ?\DateTimeImmutable $clockIn,
    ?\DateTimeImmutable $clockOut,
    int $toleranceMinutes = 5,
  ): array {
    $toleranceMinutes = max(0, $toleranceMinutes);

    if ($clockIn === NULL || $clockOut === NULL) {
      return [
        'status' => 'Onvolledig',
        'early_minutes' => 0,
        'late_minutes' => 0,
        'message' => 'In- of uitkloktijd ontbreekt.',
      ];
    }

    $earlyMinutes = max(0, (int) floor(($plannedEnd->getTimestamp() - $clockOut->getTimestamp()) / 60));
    $lateMinutes = max(0, (int) floor(($clockOut->getTimestamp() - $plannedEnd->getTimestamp()) / 60));

    if ($earlyMinutes > $toleranceMinutes) {
      return [
        'status' => 'Te vroeg uitgeklokt',
        'early_minutes' => $earlyMinutes,
        'late_minutes' => 0,
        'message' => sprintf('Uitgeklokt %d minuten voor einde dienst.', $earlyMinutes),
      ];
    }

    if ($lateMinutes > $toleranceMinutes) {
      return [
        'status' => 'Te laat uitgeklokt',
        'early_minutes' => 0,
        'late_minutes' => $lateMinutes,
        'message' => sprintf('Uitgeklokt %d minuten na einde dienst.', $lateMinutes),
      ];
    }

    return [
      'status' => 'Volgens planning',
      'early_minutes' => $earlyMinutes,
      'late_minutes' => $lateMinutes,
      'message' => 'Uitkloktijd valt binnen de ingestelde tolerantie.',
    ];
  }

}
