<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Correlates an early clock-out with a following clock-in on another project.
 */
final class ClockTransitionControl {

  /**
   * @return array{status: string, gap_minutes: int, requires_reason: bool, message: string}
   */
  public function assess(
    int $fromProjectId,
    \DateTimeImmutable $clockOut,
    ?int $toProjectId,
    ?\DateTimeImmutable $nextClockIn,
    ?string $nextGeoStatus,
    int $maxTransferMinutes = 90,
  ): array {
    $maxTransferMinutes = max(0, $maxTransferMinutes);

    if ($toProjectId === NULL || $nextClockIn === NULL) {
      return [
        'status' => 'Geen vervolgklokactie',
        'gap_minutes' => 0,
        'requires_reason' => TRUE,
        'message' => 'Geen aansluitende klokactie op een ander project gevonden.',
      ];
    }

    $gapSeconds = $nextClockIn->getTimestamp() - $clockOut->getTimestamp();
    if ($gapSeconds < 0) {
      return [
        'status' => 'Ongeldige volgorde',
        'gap_minutes' => 0,
        'requires_reason' => TRUE,
        'message' => 'De volgende inklokactie ligt voor de uitklokactie.',
      ];
    }

    $gapMinutes = (int) floor($gapSeconds / 60);

    if ($toProjectId === $fromProjectId) {
      return [
        'status' => 'Zelfde project hervat',
        'gap_minutes' => $gapMinutes,
        'requires_reason' => TRUE,
        'message' => 'Na vroeg uitklokken is opnieuw op hetzelfde project ingeklokt.',
      ];
    }

    if ($gapMinutes > $maxTransferMinutes) {
      return [
        'status' => 'Projectwissel buiten venster',
        'gap_minutes' => $gapMinutes,
        'requires_reason' => TRUE,
        'message' => sprintf('Volgende projectklokactie volgde pas na %d minuten.', $gapMinutes),
      ];
    }

    if ($nextGeoStatus !== 'Binnen zone') {
      return [
        'status' => 'Projectwissel locatie niet bevestigd',
        'gap_minutes' => $gapMinutes,
        'requires_reason' => TRUE,
        'message' => 'Er is een volgende projectklokactie, maar de locatiecontrole is niet bevestigd.',
      ];
    }

    return [
      'status' => 'Projectwissel bevestigd',
      'gap_minutes' => $gapMinutes,
      'requires_reason' => FALSE,
      'message' => sprintf('Projectwissel bevestigd: binnen %d minuten op een ander project binnen de werkzone ingeklokt.', $gapMinutes),
    ];
  }

}
