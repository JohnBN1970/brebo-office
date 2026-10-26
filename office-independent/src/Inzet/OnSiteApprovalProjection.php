<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only projection of Office-approved complete sessions for OnSite.
 * Input MUST come from authenticated Office review storage, scoped to the
 * device-token's verified person. Presence evidence itself cannot approve hours.
 */
final class OnSiteApprovalProjection {
  /**
   * @param list<array<string,mixed>> $sessions Office-reviewed sessions
   * @return array<string,string> event UUID => approved-at UTC ISO-8601
   */
  public static function forEmployee(array $sessions, string $verifiedPersonId): array {
    if (!ctype_digit($verifiedPersonId) || (int)$verifiedPersonId < 1) {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $approvals = [];
    foreach ($sessions as $session) {
      if ((string)($session['person_id'] ?? '') !== $verifiedPersonId
        || ($session['approval_status'] ?? null) !== 'approved') {
        continue;
      }
      $approvedAt = $session['approved_at'] ?? null;
      $inId = $session['in_event_id'] ?? null;
      $outId = $session['out_event_id'] ?? null;
      if (!is_string($approvedAt) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$/D', $approvedAt)
        || strtotime($approvedAt) === false) {
        continue;
      }
      // A complete reviewed session requires both valid, distinct event IDs.
      $uuid = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/D';
      if (!is_string($inId) || !is_string($outId)
        || !preg_match($uuid, $inId) || !preg_match($uuid, $outId)
        || strcasecmp($inId, $outId) === 0) {
        continue;
      }
      $approvals[$inId] = $approvedAt;
      $approvals[$outId] = $approvedAt;
    }
    return $approvals;
  }
}
