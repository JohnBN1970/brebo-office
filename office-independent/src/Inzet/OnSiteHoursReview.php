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
    if (!is_int($duration) || $duration < 0 || $duration > 86400) {
      throw new \DomainException('Sessieduur ontbreekt of vereist handmatige controle.');
    }
    $reason = is_string($reason) ? trim($reason) : '';
    if ($decision === 'reject') {
      if ($reason === '') throw new \InvalidArgumentException('Reden verplicht bij afkeuren.');
      return ['status'=>'rejected','reviewed_seconds'=>0,'reason'=>$reason];
    }
    if ($correctedSeconds !== null && ($correctedSeconds < 0 || $correctedSeconds > 86400)) {
      throw new \InvalidArgumentException('Ongeldige correctie in seconden.');
    }
    if ($correctedSeconds !== null && $correctedSeconds !== $duration && $reason === '') {
      throw new \InvalidArgumentException('Reden verplicht bij urencorrectie.');
    }
    return ['status'=>'approved','reviewed_seconds'=>$correctedSeconds ?? $duration,'reason'=>$reason !== '' ? $reason : null];
  }
}
