<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;
use DomainException;

/**
 * Persist review states alongside canonical calculation_lines, never as a
 * second set of priced calculation rows. Caller must authorize draft access.
 */
final class OfficeCalculationLineApprovalRepository {
  public function __construct(private readonly PDO $db) {}

  public static function schema(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_calc_line_approvals (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        draft_id BIGINT UNSIGNED NOT NULL,
        calculation_line_id BIGINT UNSIGNED NOT NULL,
        line_fingerprint CHAR(64) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'draft',
        author_id BIGINT UNSIGNED NOT NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        released_by BIGINT UNSIGNED NULL,
        released_at DATETIME NULL,
        note VARCHAR(1000) NOT NULL DEFAULT '',
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_draft_line (draft_id,calculation_line_id),
        KEY idx_draft_status (draft_id,status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      "CREATE TABLE IF NOT EXISTS office_calc_line_approval_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        draft_id BIGINT UNSIGNED NOT NULL,
        calculation_line_id BIGINT UNSIGNED NOT NULL,
        actor_id BIGINT UNSIGNED NOT NULL,
        event_kind VARCHAR(32) NOT NULL,
        line_fingerprint CHAR(64) NOT NULL,
        note VARCHAR(1000) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY idx_line_history (draft_id,calculation_line_id,id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
  }

  /** Must be called inside the caller's locked draft transaction. */
  public function save(int $draftId,int $lineId,int $actorId,array $state,string $event,string $note=''): void {
    if (!$this->db->inTransaction()) throw new DomainException('approval_requires_locked_transaction');
    if ($draftId<1 || $lineId<1 || $actorId<1
      || !in_array($event,['reviewed','released','revision_invalidated'],true)
      || !in_array($state['status']??'', ['draft','reviewed','released'],true)
      || !preg_match('/^[a-f0-9]{64}$/D',(string)($state['fingerprint']??''))
      || (int)($state['author_id']??0)<1
      || mb_strlen($note)>1000) throw new DomainException('invalid_approval_event');
    $q=$this->db->prepare('INSERT INTO office_calc_line_approvals
      (draft_id,calculation_line_id,line_fingerprint,status,author_id,reviewed_by,reviewed_at,released_by,released_at,note,updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())
      ON DUPLICATE KEY UPDATE line_fingerprint=VALUES(line_fingerprint),status=VALUES(status),
      author_id=VALUES(author_id),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at),
      released_by=VALUES(released_by),released_at=VALUES(released_at),note=VALUES(note),updated_at=UTC_TIMESTAMP()');
    $q->execute([$draftId,$lineId,$state['fingerprint'],$state['status'],$state['author_id'],
      $state['reviewed_by']??null,self::sqlDate($state['reviewed_at']??null),
      $state['released_by']??null,self::sqlDate($state['released_at']??null),$note]);
    $q=$this->db->prepare('INSERT INTO office_calc_line_approval_events
      (draft_id,calculation_line_id,actor_id,event_kind,line_fingerprint,note,created_at)
      VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())');
    $q->execute([$draftId,$lineId,$actorId,$event,$state['fingerprint'],$note]);
  }

  public function listForDraft(int $draftId): array {
    if ($draftId<1) throw new DomainException('invalid_draft');
    $q=$this->db->prepare('SELECT * FROM office_calc_line_approvals WHERE draft_id=? ORDER BY calculation_line_id');
    $q->execute([$draftId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  private static function sqlDate(?string $date): ?string {
    if ($date===null) return null;
    $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP',$date);
    if ($parsed===false) throw new DomainException('invalid_approval_date');
    return $parsed->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }
}
