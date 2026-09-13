<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\node\NodeInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Stores verified current building truth, proposals and immutable history. */
final class BuildingTruthRepository {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly LockBackendInterface $lock,
    private readonly BuildingObjectRepository $objects,
  ) {}

  /** Returns the current verified fact, or NULL when no truth is established. */
  public function current(int $buildingNid, string $factKey, ?int $objectId = NULL): ?array {
    $this->assertScope($buildingNid, $objectId);
    $this->ensureStorage();
    $row = $this->database->select('brebo_building_truth_current', 't')
      ->fields('t')
      ->condition('building_nid', $buildingNid)
      ->condition('scope_key', $this->scopeKey($objectId))
      ->condition('fact_key', $this->normalizeFactKey($factKey))
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $this->decodeRow($row);
  }

  /** Returns all current verified facts for a building or one canonical object. */
  public function currentFacts(int $buildingNid, ?int $objectId = NULL): array {
    $this->assertScope($buildingNid, $objectId);
    $this->ensureStorage();
    $query = $this->database->select('brebo_building_truth_current', 't')->fields('t')
      ->condition('building_nid', $buildingNid);
    if ($objectId !== NULL) {
      $query->condition('scope_key', $this->scopeKey($objectId));
    }
    $rows = $query->orderBy('scope_key')->orderBy('fact_key')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(fn(array $row): array => $this->decodeRow($row), $rows);
  }

  /**
   * Creates a non-authoritative change proposal, normally from a project.
   * Verification is deliberately separate: projects never overwrite truth.
   */
  public function propose(
    int $buildingNid,
    string $factKey,
    mixed $value,
    int $proposedBy,
    ?int $objectId = NULL,
    ?int $sourceProjectNid = NULL,
    string $sourceType = 'project',
    ?string $sourceRef = NULL,
    array $evidence = [],
    ?string $reason = NULL,
  ): int {
    $this->assertScope($buildingNid, $objectId);
    $this->assertProject($sourceProjectNid);
    $this->ensureStorage();
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('brebo_building_truth_proposal')->fields([
      'building_nid' => $buildingNid,
      'object_id' => $objectId,
      'scope_key' => $this->scopeKey($objectId),
      'fact_key' => $this->normalizeFactKey($factKey),
      'value_json' => $this->encode($value),
      'source_project_nid' => $sourceProjectNid,
      'source_type' => $this->clean($sourceType, 64) ?? 'project',
      'source_ref' => $this->clean($sourceRef, 255),
      'evidence_json' => $this->encode($evidence),
      'reason' => $this->clean($reason, 1000),
      'status' => 'pending',
      'proposed_by' => $proposedBy,
      'proposed_at' => $now,
      'verified_by' => NULL,
      'verified_at' => NULL,
      'verification_note' => NULL,
    ])->execute();
  }

  /**
   * Accepts a pending proposal and atomically promotes it to current truth.
   * The previous current truth is copied to immutable history first.
   */
  public function acceptProposal(int $proposalId, int $verifiedBy, ?string $verificationNote = NULL): array {
    $this->ensureStorage();
    $proposal = $this->loadProposal($proposalId);
    if (($proposal['status'] ?? '') !== 'pending') {
      throw new UnexpectedValueException('Only a pending building truth proposal can be accepted.');
    }

    $lockName = sprintf('brebo_building_truth:%d:%s:%s', (int) $proposal['building_nid'], $proposal['scope_key'], $proposal['fact_key']);
    if (!$this->lock->acquire($lockName, 10.0)) {
      throw new RuntimeException('Building truth is temporarily locked. Try again.');
    }

    $transaction = $this->database->startTransaction();
    try {
      // Re-read after lock acquisition to prevent two reviewers accepting the same proposal.
      $proposal = $this->loadProposal($proposalId);
      if (($proposal['status'] ?? '') !== 'pending') {
        throw new UnexpectedValueException('Building truth proposal is no longer pending.');
      }

      $now = $this->time->getRequestTime();
      $current = $this->database->select('brebo_building_truth_current', 't')->fields('t')
        ->condition('building_nid', (int) $proposal['building_nid'])
        ->condition('scope_key', (string) $proposal['scope_key'])
        ->condition('fact_key', (string) $proposal['fact_key'])
        ->execute()->fetchAssoc();

      $nextVersion = 1;
      if ($current !== FALSE) {
        $nextVersion = ((int) $current['version']) + 1;
        $this->database->insert('brebo_building_truth_history')->fields([
          'building_nid' => (int) $current['building_nid'],
          'object_id' => $current['object_id'] === NULL ? NULL : (int) $current['object_id'],
          'scope_key' => (string) $current['scope_key'],
          'fact_key' => (string) $current['fact_key'],
          'value_json' => (string) $current['value_json'],
          'version' => (int) $current['version'],
          'source_project_nid' => $current['source_project_nid'] === NULL ? NULL : (int) $current['source_project_nid'],
          'source_type' => $current['source_type'],
          'source_ref' => $current['source_ref'],
          'evidence_json' => (string) $current['evidence_json'],
          'verified_by' => (int) $current['verified_by'],
          'verified_at' => (int) $current['verified_at'],
          'valid_from' => (int) $current['valid_from'],
          'valid_to' => $now,
          'superseded_by_proposal_id' => $proposalId,
          'change_reason' => $proposal['reason'],
          'archived_at' => $now,
        ])->execute();
      }

      $values = [
        'building_nid' => (int) $proposal['building_nid'],
        'object_id' => $proposal['object_id'] === NULL ? NULL : (int) $proposal['object_id'],
        'scope_key' => (string) $proposal['scope_key'],
        'fact_key' => (string) $proposal['fact_key'],
        'value_json' => (string) $proposal['value_json'],
        'version' => $nextVersion,
        'source_project_nid' => $proposal['source_project_nid'] === NULL ? NULL : (int) $proposal['source_project_nid'],
        'source_type' => $proposal['source_type'],
        'source_ref' => $proposal['source_ref'],
        'evidence_json' => (string) $proposal['evidence_json'],
        'verified_by' => $verifiedBy,
        'verified_at' => $now,
        'valid_from' => $now,
        'changed' => $now,
      ];

      if ($current === FALSE) {
        $this->database->insert('brebo_building_truth_current')->fields($values)->execute();
      }
      else {
        $this->database->update('brebo_building_truth_current')->fields($values)
          ->condition('id', (int) $current['id'])->execute();
      }

      $updated = $this->database->update('brebo_building_truth_proposal')->fields([
        'status' => 'accepted',
        'verified_by' => $verifiedBy,
        'verified_at' => $now,
        'verification_note' => $this->clean($verificationNote, 2000),
      ])->condition('id', $proposalId)->condition('status', 'pending')->execute();
      if ($updated !== 1) {
        throw new RuntimeException('Building truth proposal acceptance lost its pending state.');
      }

      return $this->current((int) $proposal['building_nid'], (string) $proposal['fact_key'], $proposal['object_id'] === NULL ? NULL : (int) $proposal['object_id']) ?? [];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /** Rejects a proposal without changing the current truth. */
  public function rejectProposal(int $proposalId, int $verifiedBy, string $verificationNote): void {
    $this->ensureStorage();
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('brebo_building_truth_proposal')->fields([
      'status' => 'rejected',
      'verified_by' => $verifiedBy,
      'verified_at' => $now,
      'verification_note' => $this->clean($verificationNote, 2000),
    ])->condition('id', $proposalId)->condition('status', 'pending')->execute();
    if ($updated !== 1) {
      throw new UnexpectedValueException('Only a pending building truth proposal can be rejected.');
    }
  }

  public function history(int $buildingNid, string $factKey, ?int $objectId = NULL): array {
    $this->assertScope($buildingNid, $objectId);
    $this->ensureStorage();
    $rows = $this->database->select('brebo_building_truth_history', 'h')->fields('h')
      ->condition('building_nid', $buildingNid)
      ->condition('scope_key', $this->scopeKey($objectId))
      ->condition('fact_key', $this->normalizeFactKey($factKey))
      ->orderBy('version', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(fn(array $row): array => $this->decodeRow($row), $rows);
  }

  public function pendingProposals(int $buildingNid): array {
    $this->assertBuilding($buildingNid);
    $this->ensureStorage();
    $rows = $this->database->select('brebo_building_truth_proposal', 'p')->fields('p')
      ->condition('building_nid', $buildingNid)->condition('status', 'pending')
      ->orderBy('proposed_at', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(fn(array $row): array => $this->decodeRow($row), $rows);
  }

  private function loadProposal(int $proposalId): array {
    $row = $this->database->select('brebo_building_truth_proposal', 'p')->fields('p')->condition('id', $proposalId)->execute()->fetchAssoc();
    if ($row === FALSE) throw new UnexpectedValueException('Building truth proposal does not exist.');
    return $row;
  }

  private function assertScope(int $buildingNid, ?int $objectId): void {
    $this->assertBuilding($buildingNid);
    if ($objectId !== NULL) {
      $object = $this->objects->load($objectId);
      if ((int) $object['building_nid'] !== $buildingNid) {
        throw new InvalidArgumentException('Building truth object belongs to another building.');
      }
    }
  }

  private function assertBuilding(int $buildingNid): void {
    $node = $this->entityTypeManager->getStorage('node')->load($buildingNid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') {
      throw new InvalidArgumentException(sprintf('Node %d is not a BREBO building.', $buildingNid));
    }
  }

  private function assertProject(?int $projectNid): void {
    if ($projectNid === NULL) return;
    $node = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new InvalidArgumentException(sprintf('Node %d is not a BREBO project.', $projectNid));
    }
  }

  private function normalizeFactKey(string $factKey): string {
    $key = strtolower(trim($factKey));
    if ($key === '' || strlen($key) > 128 || !preg_match('/^[a-z0-9][a-z0-9_.:-]*$/', $key)) {
      throw new InvalidArgumentException('Building truth fact key is invalid.');
    }
    return $key;
  }

  private function scopeKey(?int $objectId): string {
    return $objectId === NULL ? 'building' : 'object:' . $objectId;
  }

  private function encode(mixed $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
  }

  private function decodeRow(array $row): array {
    if (array_key_exists('value_json', $row)) $row['value'] = json_decode((string) $row['value_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (array_key_exists('evidence_json', $row)) $row['evidence'] = json_decode((string) $row['evidence_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    return $row;
  }

  private function clean(?string $value, int $max): ?string {
    if ($value === NULL) return NULL;
    $value = trim($value);
    return $value === '' ? NULL : substr($value, 0, $max);
  }

  private function ensureStorage(): void {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_building_truth_current')) {
      $schema->createTable('brebo_building_truth_current', [
        'description' => 'Current verified truth for a building or canonical building object.',
        'fields' => [
          'id'=>['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE], 'building_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'object_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE], 'scope_key'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],
          'fact_key'=>['type'=>'varchar','length'=>128,'not null'=>TRUE], 'value_json'=>['type'=>'text','size'=>'big','not null'=>TRUE],
          'version'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE,'default'=>1], 'source_project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
          'source_type'=>['type'=>'varchar','length'=>64,'not null'=>FALSE], 'source_ref'=>['type'=>'varchar','length'=>255,'not null'=>FALSE],
          'evidence_json'=>['type'=>'text','size'=>'big','not null'=>TRUE], 'verified_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'verified_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE], 'valid_from'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        ],
        'primary key'=>['id'], 'unique keys'=>['truth_scope_fact'=>['building_nid','scope_key','fact_key']],
        'indexes'=>['building_nid'=>['building_nid'],'object_id'=>['object_id'],'source_project_nid'=>['source_project_nid'],'fact_key'=>['fact_key']],
      ]);
    }
    if (!$schema->tableExists('brebo_building_truth_history')) {
      $schema->createTable('brebo_building_truth_history', [
        'description' => 'Immutable superseded building truth versions.',
        'fields' => [
          'id'=>['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE], 'building_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'object_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE], 'scope_key'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],
          'fact_key'=>['type'=>'varchar','length'=>128,'not null'=>TRUE], 'value_json'=>['type'=>'text','size'=>'big','not null'=>TRUE],
          'version'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE], 'source_project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
          'source_type'=>['type'=>'varchar','length'=>64,'not null'=>FALSE], 'source_ref'=>['type'=>'varchar','length'=>255,'not null'=>FALSE],
          'evidence_json'=>['type'=>'text','size'=>'big','not null'=>TRUE], 'verified_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'verified_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE], 'valid_from'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'valid_to'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE], 'superseded_by_proposal_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
          'change_reason'=>['type'=>'text','not null'=>FALSE], 'archived_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        ],
        'primary key'=>['id'], 'unique keys'=>['truth_history_version'=>['building_nid','scope_key','fact_key','version']],
        'indexes'=>['building_nid'=>['building_nid'],'object_id'=>['object_id'],'fact_key'=>['fact_key'],'source_project_nid'=>['source_project_nid']],
      ]);
    }
    if (!$schema->tableExists('brebo_building_truth_proposal')) {
      $schema->createTable('brebo_building_truth_proposal', [
        'description' => 'Unverified proposed changes to building truth.',
        'fields' => [
          'id'=>['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE], 'building_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'object_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE], 'scope_key'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],
          'fact_key'=>['type'=>'varchar','length'=>128,'not null'=>TRUE], 'value_json'=>['type'=>'text','size'=>'big','not null'=>TRUE],
          'source_project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE], 'source_type'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],
          'source_ref'=>['type'=>'varchar','length'=>255,'not null'=>FALSE], 'evidence_json'=>['type'=>'text','size'=>'big','not null'=>TRUE],
          'reason'=>['type'=>'text','not null'=>FALSE], 'status'=>['type'=>'varchar','length'=>32,'not null'=>TRUE,'default'=>'pending'],
          'proposed_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE], 'proposed_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
          'verified_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE], 'verified_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
          'verification_note'=>['type'=>'text','not null'=>FALSE],
        ],
        'primary key'=>['id'], 'indexes'=>['building_status'=>['building_nid','status'],'object_id'=>['object_id'],'source_project_nid'=>['source_project_nid'],'fact_key'=>['fact_key']],
      ]);
    }
  }
}
