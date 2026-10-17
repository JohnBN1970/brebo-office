<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Isolated test-only decision store. Never writes to the imported source.
 * Caller is responsible for Office session authentication and reviewer authorization.
 */
final class OfficeInzetReviewDecisionRepository {
  private const ACTIONS = ['link_person', 'link_project', 'propose_person', 'propose_project', 'exclude'];

  public function __construct(private PDO $db, private PDO $sourceDb) {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
      || $sourceDb->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
      throw new \InvalidArgumentException('Only a private SQLite review store is supported.');
    }
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function install(): void {
    $this->db->exec('CREATE TABLE IF NOT EXISTS inzet_review_decisions (
      issue_id INTEGER PRIMARY KEY,
      action TEXT NOT NULL,
      target_id INTEGER,
      note TEXT NOT NULL,
      reviewer_id INTEGER NOT NULL,
      revision INTEGER NOT NULL DEFAULT 1,
      updated_at TEXT NOT NULL
    )');
    $this->db->exec('CREATE TABLE IF NOT EXISTS inzet_review_decision_audit (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      issue_id INTEGER NOT NULL,
      action TEXT NOT NULL,
      target_id INTEGER,
      note TEXT NOT NULL,
      reviewer_id INTEGER NOT NULL,
      revision INTEGER NOT NULL,
      recorded_at TEXT NOT NULL
    )');
  }

  /**
   * @return array{issue_id:int,action:string,target_id:?int,note:string,reviewer_id:int,revision:int}
   */
  public function save(
    int $issueId,
    string $action,
    ?int $targetId,
    string $note,
    int $reviewerId,
    int $expectedRevision
  ): array {
    if ($issueId < 1 || $reviewerId < 1 || $expectedRevision < 0
      || !in_array($action, self::ACTIONS, true)) {
      throw new \InvalidArgumentException('Invalid review decision.');
    }
    if (in_array($action, ['link_person', 'link_project'], true)) {
      if ($targetId === null || $targetId < 1) {
        throw new \InvalidArgumentException('A verified target ID is required.');
      }
    } elseif ($targetId !== null) {
      throw new \InvalidArgumentException('Unexpected target ID.');
    }
    $note = trim($note);
    if (strlen($note) > 2000 || ($action === 'exclude' && $note === '')) {
      throw new \InvalidArgumentException('Invalid decision note.');
    }

    // Only real source issues can receive decisions.
    $sourceIssue = $this->sourceDb->prepare('SELECT 1 FROM review_issues WHERE id = ?');
    $sourceIssue->execute([$issueId]);
    if ($sourceIssue->fetchColumn() === false) {
      throw new \InvalidArgumentException('Unknown source issue.');
    }
    // Never accept an unchecked Office identity reference.
    if (in_array($action, ['link_person', 'link_project'], true)) {
      throw new \LogicException('Office target verification is not connected.');
    }

    $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
      $stmt = $this->db->prepare('SELECT revision FROM inzet_review_decisions WHERE issue_id = ?');
      $stmt->execute([$issueId]);
      $current = $stmt->fetchColumn();
      $revision = $current === false ? 0 : (int)$current;
      if ($revision !== $expectedRevision) {
        throw new RuntimeException('Review decision changed; reload before saving.');
      }
      $next = $revision + 1;
      $now = gmdate('Y-m-d\TH:i:s\Z');
      $stmt = $this->db->prepare('INSERT INTO inzet_review_decisions
        (issue_id, action, target_id, note, reviewer_id, revision, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(issue_id) DO UPDATE SET
          action=excluded.action, target_id=excluded.target_id, note=excluded.note,
          reviewer_id=excluded.reviewer_id, revision=excluded.revision,
          updated_at=excluded.updated_at');
      $stmt->execute([$issueId, $action, $targetId, $note, $reviewerId, $next, $now]);
      $stmt = $this->db->prepare('INSERT INTO inzet_review_decision_audit
        (issue_id, action, target_id, note, reviewer_id, revision, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([$issueId, $action, $targetId, $note, $reviewerId, $next, $now]);
      $this->db->commit();
      return [
        'issue_id'=>$issueId, 'action'=>$action, 'target_id'=>$targetId,
        'note'=>$note, 'reviewer_id'=>$reviewerId, 'revision'=>$next,
      ];
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  /** @return array<string,mixed>|null */
  public function find(int $issueId): ?array {
    if ($issueId < 1) throw new \InvalidArgumentException('Invalid issue ID.');
    $stmt = $this->db->prepare('SELECT issue_id, action, target_id, note, reviewer_id, revision, updated_at
      FROM inzet_review_decisions WHERE issue_id = ?');
    $stmt->execute([$issueId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
  }

  /** @return array{open:int,reviewed:int} */
  public function counts(int $sourceIssueCount): array {
    if ($sourceIssueCount < 0) throw new \InvalidArgumentException('Invalid source count.');
    $reviewed = (int)$this->db->query('SELECT COUNT(*) FROM inzet_review_decisions')->fetchColumn();
    return ['open'=>max(0, $sourceIssueCount - $reviewed), 'reviewed'=>$reviewed];
  }
}
