<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Pure Office-native hours review validation. Never treats evidence as approval. */
final class OnSiteHoursReviewDecision {
  /** @param array<string,mixed> $session */
  public function decide(array $session, string $decision, ?int $correctedSeconds = null, ?string $reason = null): array {
    if (!in_array($decision, ['approve', 'reject'], true)) {
      throw new \InvalidArgumentException('Invalid review decision');
    }
    if (($session['approval_status'] ?? null) !== 'pending'
      || empty($session['in_event_id']) || empty($session['out_event_id'])) {
      throw new \DomainException('Only complete pending sessions can be reviewed');
    }
    $duration = $session['duration_seconds'] ?? null;
    if (!is_int($duration) || $duration < 1 || $duration > 86400) {
      throw new \DomainException('Invalid session duration');
    }
    $reason = is_string($reason) ? trim($reason) : '';
    if ($decision === 'reject') {
      if ($reason === '') throw new \InvalidArgumentException('Rejection requires a reason');
      return ['status'=>'rejected','reviewed_seconds'=>0,'reason'=>$reason];
    }
    if ($correctedSeconds !== null && ($correctedSeconds < 0 || $correctedSeconds > 86400)) {
      throw new \InvalidArgumentException('Invalid corrected duration');
    }
    if ($correctedSeconds !== null && $correctedSeconds !== $duration && $reason === '') {
      throw new \InvalidArgumentException('Correction requires a reason');
    }
    return [
      'status'=>'approved',
      'reviewed_seconds'=>$correctedSeconds ?? $duration,
      'reason'=>$reason !== '' ? $reason : null,
    ];
  }
}
