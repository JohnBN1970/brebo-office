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
      foreach ([$inId, $outId] as $id) {
        if (!is_string($id) || !preg_match('/^[a-fA-F0-9-]{36}$/D', $id)) {
          continue;
        }
        $approvals[$id] = $approvedAt;
      }
    }
    return $approvals;
  }
}
