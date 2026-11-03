<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure decision logic for presence review. Only completed sessions may be
 * approved; financial booking and persistence require separate authorized flows.
 */
final class OnSiteHoursReview {
  /**
   * @param array<string,mixed> $session
   * @return array{status:string,reviewed_seconds:int,reason:?string}
   */
  public function decide(array $session, string $decision, ?int $correctedSeconds = null, ?string $reason = null): array {
    if (!in_array($decision, ['approve', 'reject'], true)) {
      throw new \InvalidArgumentException('Ongeldige beoordeling.');
    }
    if (($session['approval_status'] ?? null) !== 'pending'
      || empty($session['in_event_id']) || empty($session['out_event_id'])) {
      throw new \DomainException('Alleen volledige, nog niet beoordeelde sessies kunnen worden beoordeeld.');
    }
    $duration = $session['duration_seconds'] ?? null;
    if (!is_int($duration) || $duration < 0) {
      throw new \DomainException('Sessieduur ontbreekt of is ongeldig.');
    }
    // A long geofence interval may reflect a forgotten checkout.
    // Never accept it as-is; a manager must explicitly enter corrected time
    // and document why, or reject it.
    if ($duration > 86400 && $decision === 'approve' && ($correctedSeconds === null || trim((string)$reason) === '')) {
      throw new \DomainException('Lange sessie: expliciete correctie met toelichting vereist.');
    }
    $reason = is_string($reason) ? trim($reason) : '';
    if ($decision === 'reject') {
      return ['status'=>'rejected','reviewed_seconds'=>0,'reason'=>$reason !== '' ? $reason : null];
    }
    if ($correctedSeconds !== null && ($correctedSeconds < 0 || $correctedSeconds > 86400)) {
      throw new \InvalidArgumentException('Ongeldige correctie in seconden.');
    }
    return ['status'=>'approved','reviewed_seconds'=>$correctedSeconds ?? $duration,'reason'=>$reason !== '' ? $reason : null];
  }
}
