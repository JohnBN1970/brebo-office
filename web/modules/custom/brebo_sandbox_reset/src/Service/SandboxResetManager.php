<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/** Safely removes sandbox content while preserving software and configuration. */
final class SandboxResetManager {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly StateInterface $state,
    private readonly QueueFactory $queueFactory,
  ) {}

  /** @return array<string, int|string> */
  public function preview(string $scope): array {
    $mailIds = $this->mailIntakeCommunicationIds();
    $objectIds = $this->objectIdsForScope($scope);

    return [
      'scope' => $scope,
      'mail_communications' => count($mailIds),
      'mailbox_rows' => $this->countRows('brebo_mailbox_message', 'communication_id', $mailIds),
      'mail_tags' => $this->countRows('brebo_mail_tag', 'communication_id', $mailIds),
      'document_links' => $this->countRows('brebo_document_communication', 'communication_nid', $mailIds),
      'projects' => count($this->nodeIdsByBundle('brebo_project', $scope)),
      'buildings' => count($this->nodeIdsByBundle('brebo_building', $scope)),
      'object_references' => $this->countNodeReferences($objectIds),
      'address_scope_proposals' => $this->countAddressScopeProposals(),
    ];
  }

  /** @return array<string, int|string> */
  public function reset(string $scope): array {
    $allowed = ['mail_content', 'mail_content_zoho', 'projects', 'buildings', 'projects_buildings'];
    if (!in_array($scope, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Onbekende sandbox-resetscope.');
    }

    $preview = $this->preview($scope);
    $transaction = $this->database->startTransaction();

    try {
      if (str_starts_with($scope, 'mail_content')) {
        $this->resetMail($scope);
      }
      else {
        $objectIds = $this->objectIdsForScope($scope);
        if ($objectIds !== []) {
          $this->clearNodeReferences($objectIds);
          $storage = $this->entityTypeManager->getStorage('node');
          $nodes = $storage->loadMultiple($objectIds);
          if ($nodes !== []) {
            $storage->delete($nodes);
          }
        }
        // These are unapproved machine proposals, not business objects. They
        // must not survive an object reset and point at a clean baseline.
        if ($this->database->schema()->tableExists('brebo_address_scope_intake')) {
          $this->database->delete('brebo_address_scope_intake')->execute();
        }
      }
    }
    catch (\Throwable $e) {
      if (method_exists($transaction, 'rollBack')) {
        $transaction->rollBack();
      }
      throw $e;
    }

    return $preview + ['result' => 'completed'];
  }

  private function resetMail(string $scope): void {
    $ids = $this->mailIntakeCommunicationIds();
    if ($ids !== []) {
      $this->deleteRows('brebo_mailbox_message', 'communication_id', $ids);
      $this->deleteRows('brebo_mail_tag', 'communication_id', $ids);
      $this->deleteRows('brebo_document_communication', 'communication_nid', $ids);
      $storage = $this->entityTypeManager->getStorage('node');
      $nodes = $storage->loadMultiple($ids);
      if ($nodes !== []) {
        $storage->delete($nodes);
      }
    }

    $this->queueFactory->get('brebo_mail_intake_process')->deleteQueue();
    $this->state->set('brebo_mail_intake.zoho_migration_enabled', FALSE);
    $this->state->delete('brebo_mail_intake.zoho_pilot_before_count');
    if ($scope === 'mail_content_zoho') {
      $this->state->delete('brebo_mail_intake.zoho_migration_before_uid');
      $this->state->delete('brebo_mail_intake.zoho_migration_complete');
      $this->state->delete('brebo_mail_intake.zoho_migration_last_uid');
    }
  }

  /** @return int[] */
  private function objectIdsForScope(string $scope): array {
    $ids = [];
    if (in_array($scope, ['projects', 'projects_buildings'], TRUE)) {
      $ids = array_merge($ids, $this->nodeIdsByBundle('brebo_project', $scope));
    }
    if (in_array($scope, ['buildings', 'projects_buildings'], TRUE)) {
      $ids = array_merge($ids, $this->nodeIdsByBundle('brebo_building', $scope));
    }
    return array_values(array_unique(array_map('intval', $ids)));
  }

  /** @return int[] */
  private function nodeIdsByBundle(string $bundle, string $scope): array {
    $applies = ($bundle === 'brebo_project' && in_array($scope, ['projects', 'projects_buildings'], TRUE))
      || ($bundle === 'brebo_building' && in_array($scope, ['buildings', 'projects_buildings'], TRUE));
    if (!$applies) {
      return [];
    }
    return array_values(array_map('intval', $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->execute()));
  }

  /**
   * Clears node entity-reference fields that point to objects being removed.
   *
   * This prevents dangling references while preserving the referring records.
   *
   * @param int[] $targetIds
   */
  private function clearNodeReferences(array $targetIds): void {
    if ($targetIds === []) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $fieldMap = $this->entityFieldManager->getFieldMapByFieldType('entity_reference')['node'] ?? [];
    foreach (array_keys($fieldMap) as $fieldName) {
      $definitions = $this->entityFieldManager->getFieldStorageDefinitions('node');
      $definition = $definitions[$fieldName] ?? NULL;
      if (!$definition || $definition->getSetting('target_type') !== 'node') {
        continue;
      }
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition($fieldName . '.target_id', $targetIds, 'IN')->execute();
      foreach ($storage->loadMultiple($ids) as $node) {
        $kept = [];
        foreach ($node->get($fieldName) as $item) {
          $targetId = (int) $item->target_id;
          if (!in_array($targetId, $targetIds, TRUE)) {
            $kept[] = ['target_id' => $targetId];
          }
        }
        $node->set($fieldName, $kept);
        $node->save();
      }
    }
  }

  /** @param int[] $targetIds */
  private function countNodeReferences(array $targetIds): int {
    if ($targetIds === []) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions('node');
    $fieldMap = $this->entityFieldManager->getFieldMapByFieldType('entity_reference')['node'] ?? [];
    $count = 0;
    foreach (array_keys($fieldMap) as $fieldName) {
      $definition = $definitions[$fieldName] ?? NULL;
      if ($definition && $definition->getSetting('target_type') === 'node') {
        $count += (int) $storage->getQuery()->accessCheck(FALSE)->condition($fieldName . '.target_id', $targetIds, 'IN')->count()->execute();
      }
    }
    return $count;
  }

  private function countAddressScopeProposals(): int {
    if (!$this->database->schema()->tableExists('brebo_address_scope_intake')) {
      return 0;
    }
    return (int) $this->database->select('brebo_address_scope_intake', 'i')->countQuery()->execute()->fetchField();
  }

  /** @return int[] */
  private function mailIntakeCommunicationIds(): array {
    if (!$this->database->schema()->tableExists('node__field_brebo_source_id')) {
      return [];
    }
    $query = $this->database->select('node_field_data', 'n');
    $query->join('node__field_brebo_source_id', 'sid', 'sid.entity_id = n.nid AND sid.deleted = 0');
    $query->fields('n', ['nid']);
    $query->condition('n.type', 'brebo_communication');
    $query->condition('sid.field_brebo_source_id_value', '', '<>');
    if ($this->database->schema()->tableExists('node__field_brebo_comm_channel')) {
      $query->join('node__field_brebo_comm_channel', 'ch', 'ch.entity_id = n.nid AND ch.deleted = 0');
      $query->condition('ch.field_brebo_comm_channel_value', 'E-mail');
    }
    return array_values(array_unique(array_map('intval', $query->execute()->fetchCol())));
  }

  /** @param int[] $ids */
  private function countRows(string $table, string $column, array $ids): int {
    if ($ids === [] || !$this->database->schema()->tableExists($table)) {
      return 0;
    }
    return (int) $this->database->select($table, 'r')->condition($column, $ids, 'IN')->countQuery()->execute()->fetchField();
  }

  /** @param int[] $ids */
  private function deleteRows(string $table, string $column, array $ids): void {
    if ($ids !== [] && $this->database->schema()->tableExists($table)) {
      $this->database->delete($table)->condition($column, $ids, 'IN')->execute();
    }
  }

}
