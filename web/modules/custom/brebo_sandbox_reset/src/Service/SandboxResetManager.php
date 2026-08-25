<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/** Safely removes sandbox content while preserving software and configuration. */
final class SandboxResetManager {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly QueueFactory $queueFactory,
  ) {}

  /**
   * Returns a non-destructive preview for the selected reset scope.
   *
   * @return array<string, int|string>
   */
  public function preview(string $scope): array {
    $ids = $this->mailIntakeCommunicationIds();

    return [
      'scope' => $scope,
      'mail_communications' => count($ids),
      'mailbox_rows' => $this->countRows('brebo_mailbox_message', 'communication_id', $ids),
      'mail_tags' => $this->countRows('brebo_mail_tag', 'communication_id', $ids),
      'document_links' => $this->countRows('brebo_document_communication', 'communication_nid', $ids),
    ];
  }

  /**
   * Executes the selected sandbox content reset.
   *
   * @return array<string, int|string>
   */
  public function reset(string $scope): array {
    if (!in_array($scope, ['mail_content', 'mail_content_zoho'], TRUE)) {
      throw new \InvalidArgumentException('Onbekende sandbox-resetscope.');
    }

    $preview = $this->preview($scope);
    $ids = $this->mailIntakeCommunicationIds();
    $transaction = $this->database->startTransaction();

    try {
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

      // Pending intake work belongs to the content being reset.
      $this->queueFactory->get('brebo_mail_intake_process')->deleteQueue();

      // Never arm a historical migration as a side effect of a reset.
      $this->state->set('brebo_mail_intake.zoho_migration_enabled', FALSE);
      $this->state->delete('brebo_mail_intake.zoho_pilot_before_count');

      if ($scope === 'mail_content_zoho') {
        $this->state->delete('brebo_mail_intake.zoho_migration_before_uid');
        $this->state->delete('brebo_mail_intake.zoho_migration_complete');
        $this->state->delete('brebo_mail_intake.zoho_migration_last_uid');
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

  /**
   * Finds only Communications created from a traceable Mail Intake source.
   *
   * Hand-created Communication records without source_id are deliberately kept.
   *
   * @return int[]
   */
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
    return (int) $this->database->select($table, 'r')
      ->condition($column, $ids, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /** @param int[] $ids */
  private function deleteRows(string $table, string $column, array $ids): void {
    if ($ids === [] || !$this->database->schema()->tableExists($table)) {
      return;
    }
    $this->database->delete($table)->condition($column, $ids, 'IN')->execute();
  }

}
