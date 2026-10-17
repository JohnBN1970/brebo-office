<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Compares clock-out against planned end without storing clock events. */
final class ClockTimeControl {
  /** @return array{status:string,early_minutes:int,late_minutes:int,message:string} */
  public function assess(\DateTimeImmutable $plannedStart, \DateTimeImmutable $plannedEnd, ?\DateTimeImmutable $clockIn, ?\DateTimeImmutable $clockOut, int $toleranceMinutes = 5): array {
    $toleranceMinutes = max(0, $toleranceMinutes);
    if ($clockIn === null || $clockOut === null) return ['status' => 'Onvolledig', 'early_minutes' => 0, 'late_minutes' => 0, 'message' => 'In- of uitkloktijd ontbreekt.'];
    $early = max(0, (int) floor(($plannedEnd->getTimestamp() - $clockOut->getTimestamp()) / 60));
    $late = max(0, (int) floor(($clockOut->getTimestamp() - $plannedEnd->getTimestamp()) / 60));
    if ($early > $toleranceMinutes) return ['status' => 'Te vroeg uitgeklokt', 'early_minutes' => $early, 'late_minutes' => 0, 'message' => sprintf('Uitgeklokt %d minuten voor einde dienst.', $early)];
    if ($late > $toleranceMinutes) return ['status' => 'Te laat uitgeklokt', 'early_minutes' => 0, 'late_minutes' => $late, 'message' => sprintf('Uitgeklokt %d minuten na einde dienst.', $late)];
    return ['status' => 'Volgens planning', 'early_minutes' => $early, 'late_minutes' => $late, 'message' => 'Uitkloktijd valt binnen de ingestelde tolerantie.'];
  }
}
