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
    $websiteTargets = $scope === 'website_europakozijn' ? $this->websiteEuropakozijnTargets() : $this->emptyWebsiteTargets();

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
      'website_intake_records' => count($websiteTargets['record_ids']),
      'website_intake_runs' => count($websiteTargets['run_ids']),
      'website_intake_decisions' => $this->countRows('brebo_data_intake_decision', 'record_id', $websiteTargets['record_ids']),
      'website_intake_candidates' => $this->countRows('brebo_masterdata_candidate', 'record_id', $websiteTargets['record_ids']),
      'website_intake_files' => count($websiteTargets['file_ids']),
      'website_intake_leads' => count($websiteTargets['lead_ids']),
    ];
  }

  /** @return array<string, int|string> */
  public function reset(string $scope): array {
    $allowed = ['mail_content', 'mail_content_zoho', 'projects', 'buildings', 'projects_buildings', 'website_europakozijn'];
    if (!in_array($scope, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Onbekende sandbox-resetscope.');
    }

    $preview = $this->preview($scope);
    $transaction = $this->database->startTransaction();

    try {
      if (str_starts_with($scope, 'mail_content')) {
        $this->resetMail($scope);
      }
      elseif ($scope === 'website_europakozijn') {
        $this->resetWebsiteEuropakozijn();
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

  private function resetWebsiteEuropakozijn(): void {
    $targets = $this->websiteEuropakozijnTargets();

    if ($targets['record_ids'] !== []) {
      $this->deleteRows('brebo_data_intake_decision', 'record_id', $targets['record_ids']);
      $this->deleteRows('brebo_masterdata_candidate', 'record_id', $targets['record_ids']);
      $this->deleteRows('brebo_data_record', 'id', $targets['record_ids']);
    }
    if ($targets['run_ids'] !== []) {
      $this->deleteRows('brebo_data_ingest_run', 'id', $targets['run_ids']);
    }
    if ($targets['lead_ids'] !== []) {
      $storage = $this->entityTypeManager->getStorage('node');
      $nodes = $storage->loadMultiple($targets['lead_ids']);
      if ($nodes !== []) {
        $storage->delete($nodes);
      }
    }
    if ($targets['file_ids'] !== []) {
      $storage = $this->entityTypeManager->getStorage('file');
      $files = $storage->loadMultiple($targets['file_ids']);
      if ($files !== []) {
        $storage->delete($files);
      }
    }
  }

  /** @return array{record_ids:int[],run_ids:int[],file_ids:int[],lead_ids:int[]} */
  private function websiteEuropakozijnTargets(): array {
    $recordIds = [];
    $runIds = [];
    $fileIds = [];

    if ($this->database->schema()->tableExists('brebo_data_record')) {
      $query = $this->database->select('brebo_data_record', 'r');
      $query->fields('r', ['id', 'run_id', 'payload']);
      $query->condition('r.record_type', 'source_neutral_intake');
      $query->condition('r.payload', '%"classification":"website_project_request"%', 'LIKE');
      $query->condition('r.payload', '%"source":"website"%', 'LIKE');
      foreach ($query->execute() as $row) {
        $recordIds[] = (int) $row->id;
        $runIds[] = (int) $row->run_id;
        $payload = json_decode((string) $row->payload, TRUE);
        $fileId = $payload['envelope']['payload']['file_id'] ?? NULL;
        if (is_numeric($fileId) && (int) $fileId > 0) {
          $fileIds[] = (int) $fileId;
        }
      }
    }

    $leadIds = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', 'brebo_opportunity');
    if (isset($fieldDefinitions['field_brebo_opp_source'])) {
      $leadIds = array_values(array_map('intval', $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_opportunity')
        ->condition('field_brebo_opp_source', 'Website - Europakozijn')
        ->execute()));
    }

    return [
      'record_ids' => array_values(array_unique($recordIds)),
      'run_ids' => array_values(array_unique($runIds)),
      'file_ids' => array_values(array_unique($fileIds)),
      'lead_ids' => array_values(array_unique($leadIds)),
    ];
  }

  /** @return array{record_ids:array<int>,run_ids:array<int>,file_ids:array<int>,lead_ids:array<int>} */
  private function emptyWebsiteTargets(): array {
    return ['record_ids' => [], 'run_ids' => [], 'file_ids' => [], 'lead_ids' => []];
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

  /** @param int[] $targetIds */
  private function clearNodeReferences(array $targetIds): void {
    if ($targetIds === []) {
      return;
    }

    foreach ($this->nodeReferenceFields() as $fieldName) {
      $column = $fieldName . '_target_id';
      foreach (['node__' . $fieldName, 'node_revision__' . $fieldName] as $table) {
        if (!$this->database->schema()->tableExists($table) || !$this->database->schema()->fieldExists($table, $column)) {
          continue;
        }
        $this->database->delete($table)
          ->condition($column, $targetIds, 'IN')
          ->execute();
      }
    }
  }

  /** @param int[] $targetIds */
  private function countNodeReferences(array $targetIds): int {
    if ($targetIds === []) {
      return 0;
    }

    $count = 0;
    foreach ($this->nodeReferenceFields() as $fieldName) {
      $table = 'node__' . $fieldName;
      $column = $fieldName . '_target_id';
      if (!$this->database->schema()->tableExists($table) || !$this->database->schema()->fieldExists($table, $column)) {
        continue;
      }
      $count += (int) $this->database->select($table, 'r')
        ->condition($column, $targetIds, 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    return $count;
  }

  /** @return string[] */
  private function nodeReferenceFields(): array {
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions('node');
    $fieldMap = $this->entityFieldManager->getFieldMapByFieldType('entity_reference')['node'] ?? [];
    $fields = [];
    foreach (array_keys($fieldMap) as $fieldName) {
      $definition = $definitions[$fieldName] ?? NULL;
      if ($definition && $definition->getSetting('target_type') === 'node') {
        $fields[] = $fieldName;
      }
    }
    return $fields;
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
