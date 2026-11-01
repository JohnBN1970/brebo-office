<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;

require_once dirname(__DIR__) . '/Project/OfficeNativeProjectAccess.php';

/**
 * Persist a first review of a completed OnSite interval.
 * Raw events remain immutable. No invoice or payroll booking occurs here.
 */
final class OnSiteHoursReviewRepository {
  public function __construct(private readonly PDO $db) {}

  /**
   * @param array<string,mixed> $session Projected from authenticated OnSite evidence.
   * @return array{status:string,reviewed_seconds:int,reason:?string}
   */
  public function review(
    int $operatorId,
    array $session,
    string $decision,
    ?int $correctedSeconds,
    ?string $reason,
    string $requestKey
  ): array {
    $projectId = filter_var($session['project_id'] ?? null, FILTER_VALIDATE_INT);
    $personId = filter_var($session['person_id'] ?? null, FILTER_VALIDATE_INT);
    $inId = $session['in_event_id'] ?? null;
    $outId = $session['out_event_id'] ?? null;
    if (!is_int($projectId) || $projectId < 1 || !is_int($personId) || $personId < 1
      || !is_string($inId) || $inId === '' || !is_string($outId) || $outId === ''
      || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
      throw new \InvalidArgumentException('Ongeldige urenbeoordeling.');
    }
    if (!(new OfficeNativeProjectAccess($this->db))->canManage($operatorId, $projectId)) {
      throw new \DomainException('Geen bevoegdheid voor urenacceptatie.');
    }

    // Never trust a session supplied by a browser: rebuild it from OnSite inbox.
    require_once __DIR__ . '/OnSitePresenceIntervalProjector.php';
    require_once __DIR__ . '/OnSiteHoursReview.php';
    $stmt = $this->db->prepare(
      'SELECT event_id, person_id, project_id, zone_id, event_kind, occurred_at_utc
       FROM office_onsite_event_inbox WHERE project_id = :project_id
       ORDER BY occurred_at_utc, event_id LIMIT 10000'
    );
    $stmt->execute([':project_id' => (string)$projectId]);
    $projected = (new OnSitePresenceIntervalProjector())->project($stmt->fetchAll(PDO::FETCH_ASSOC));
    $verified = null;
    foreach ($projected['intervals'] as $interval) {
      if ((int)$interval['person_id'] === $personId
        && (string)$interval['arrival_event_id'] === $inId
        && (string)$interval['departure_event_id'] === $outId) {
        $verified = [
          'project_id' => $projectId,
          'person_id' => $personId,
          'in_event_id' => $inId,
          'out_event_id' => $outId,
          'duration_seconds' => (int)$interval['elapsed_seconds'],
          'approval_status' => 'pending',
          'zone_id' => (string)($interval['zone_id'] ?? ''),
        ];
        break;
      }
    }
    if ($verified === null) throw new \DomainException('OnSite-sessie niet gevonden of onvolledig.');
    $review = (new OnSiteHoursReview())->decide($verified, $decision, $correctedSeconds, $reason);
    $fingerprint = hash('sha256', json_encode([
      $projectId, $personId, $inId, $outId, $decision, $correctedSeconds, $reason,
    ], JSON_THROW_ON_ERROR));

    $this->db->beginTransaction();
    try {
      $check = $this->db->prepare(
        'SELECT request_fingerprint, approval_status, reviewed_seconds, reason
         FROM office_onsite_hours_reviews WHERE request_key = ? FOR UPDATE'
      );
      $check->execute([$requestKey]);
      $prior = $check->fetch(PDO::FETCH_ASSOC);
      if ($prior !== false) {
        if (!hash_equals((string)$prior['request_fingerprint'], $fingerprint)) {
          throw new \DomainException('Verzoekcode is al voor een andere beoordeling gebruikt.');
        }
        // A retry must still refer to the same original OnSite evidence.
        // Never return another review merely because its request key matches.
        $sameEvidence = $this->db->prepare(
          'SELECT 1 FROM office_onsite_hours_reviews
           WHERE request_key = ? AND person_id = ? AND project_id = ?
             AND in_event_id = ? AND out_event_id = ? LIMIT 1'
        );
        $sameEvidence->execute([$requestKey, $personId, (string)$projectId, $inId, $outId]);
        if ($sameEvidence->fetchColumn() === false) {
          throw new \DomainException('Verzoekcode hoort bij een andere OnSite-sessie.');
        }
        $this->db->commit();
        return [
          'status' => (string)$prior['approval_status'],
          'reviewed_seconds' => (int)$prior['reviewed_seconds'],
          'reason' => $prior['reason'] === null ? null : (string)$prior['reason'],
        ];
      }
      $insert = $this->db->prepare(
        'INSERT INTO office_onsite_hours_reviews
         (person_id, project_id, zone_id, in_event_id, out_event_id,
          approval_status, reviewed_seconds, reviewer_operator_id, approved_at,
          reviewed_at, reason, revision, request_key, request_fingerprint)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, 1, ?, ?)'
      );
      $insert->execute([
        $personId, (string)$projectId, $verified['zone_id'], $inId, $outId,
        $review['status'], $review['reviewed_seconds'], (string)$operatorId,
        $review['status'] === 'approved' ? gmdate('Y-m-d H:i:s') : null,
        $review['reason'], $requestKey, $fingerprint,
      ]);
      $this->db->commit();
      return $review;
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
