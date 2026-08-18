<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Validates equipment and vehicle bookings before operational release.
 */
final class WorkforceResourceControl {

  /**
   * @param array<string, mixed> $resource
   * @param array<string, mixed> $booking
   * @param array<int, array<string, mixed>> $existing
   * @return array{status: string, blocks: array<int, string>, warnings: array<int, string>}
   */
  public function assess(array $resource, array $booking, array $existing, string $driverQualification = 'Passend'): array {
    $blocks = [];
    $warnings = [];
    $start = new \DateTimeImmutable((string) $booking['start']);
    $end = new \DateTimeImmutable((string) $booking['end']);

    if ($end <= $start) {
      $blocks[] = 'einde ligt niet na start';
    }
    if (empty($resource['active'])) {
      $blocks[] = 'middel is niet actief planbaar';
    }
    if (in_array((string) ($resource['status'] ?? ''), ['Onderhoud', 'Defect', 'Buiten dienst'], TRUE)) {
      $blocks[] = 'operationele status is ' . strtolower((string) $resource['status']);
    }

    $inspection = trim((string) ($resource['inspection_until'] ?? ''));
    if ($inspection !== '' && new \DateTimeImmutable($inspection) < new \DateTimeImmutable($start->format('Y-m-d'))) {
      $blocks[] = 'keuring is verlopen';
    }
    elseif ($inspection !== '' && new \DateTimeImmutable($inspection) <= $start->modify('+30 days')) {
      $warnings[] = 'keuring verloopt binnen 30 dagen';
    }

    $maintenance = trim((string) ($resource['maintenance_due'] ?? ''));
    if ($maintenance !== '' && new \DateTimeImmutable($maintenance) < new \DateTimeImmutable($start->format('Y-m-d'))) {
      $blocks[] = 'onderhoud is achterstallig';
    }
    elseif ($maintenance !== '' && new \DateTimeImmutable($maintenance) <= $start->modify('+14 days')) {
      $warnings[] = 'onderhoud vervalt binnen 14 dagen';
    }

    foreach ($existing as $reservation) {
      if (in_array((string) ($reservation['status'] ?? ''), ['Geannuleerd', 'Retour'], TRUE)) {
        continue;
      }
      $otherStart = new \DateTimeImmutable((string) $reservation['start']);
      $otherEnd = new \DateTimeImmutable((string) $reservation['end']);
      if ($start < $otherEnd && $end > $otherStart) {
        $blocks[] = 'middel is in dezelfde periode al gereserveerd';
        break;
      }
    }

    if (!empty($resource['requires_skill'])) {
      if ($driverQualification === 'Blokkade') {
        $blocks[] = 'bestuurder mist de vereiste bevoegdheid';
      }
      elseif ($driverQualification === 'Waarschuwing') {
        $warnings[] = 'bestuurdersbevoegdheid verloopt binnen 30 dagen';
      }
    }

    return [
      'status' => $blocks !== [] ? 'Blokkade' : ($warnings !== [] ? 'Waarschuwing' : 'Vrijgegeven'),
      'blocks' => $blocks,
      'warnings' => $warnings,
    ];
  }

}
