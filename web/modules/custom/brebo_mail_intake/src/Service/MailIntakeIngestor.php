<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/** Registers normalized mail as source evidence in Communication. */
final class MailIntakeIngestor {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly Connection $database,
  ) {}

  public function ingest(array $mail): array {
    $this->ensureHtmlBodyField();
    $sourceId = trim((string) ($mail['source_id'] ?? ''));
    $subject = trim((string) ($mail['subject'] ?? ''));
    $body = trim((string) ($mail['body'] ?? ''));
    if ($sourceId === '' || $subject === '' || $body === '') {
      throw new \InvalidArgumentException('source_id, subject en body zijn verplicht voor Mail Intake.');
    }

    $sourceHash = trim((string) ($mail['source_hash'] ?? ''));
    if ($sourceHash === '') {
      $sourceHash = hash('sha256', implode("\n", [$sourceId, $subject, $body, trim((string) ($mail['from'] ?? '')), trim((string) ($mail['received_at'] ?? ''))]));
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'brebo_communication');
    $or = $query->orConditionGroup()->condition('field_brebo_source_id', $sourceId)->condition('field_brebo_source_hash', $sourceHash);
    $query->condition($or)->range(0, 1);
    $existingIds = $query->execute();
    if ($existingIds) {
      $existingId = (int) reset($existingIds);
      $this->ensureMailboxProjection($existingId, $mail);
      return ['state' => 'duplicate', 'node_id' => $existingId, 'duplicate_of' => $existingId, 'source_hash' => $sourceHash];
    }

    $ownerUid = (int) (getenv('BREBO_MAIL_INTAKE_UID') ?: $this->currentUser->id());
    if ($ownerUid <= 0 || !$this->entityTypeManager->getStorage('user')->load($ownerUid)) {
      throw new \RuntimeException('BREBO_MAIL_INTAKE_UID ontbreekt of verwijst niet naar een geldige Drupal-gebruiker.');
    }

    $direction = $this->mailDirection($mail);
    $htmlBody = trim((string) ($mail['body_html'] ?? ''));
    $values = [
      'type' => 'brebo_communication', 'title' => $subject, 'uid' => $ownerUid, 'status' => 1,
      'field_brebo_source_id' => $sourceId, 'field_brebo_source_hash' => $sourceHash,
      'field_brebo_comm_channel' => 'E-mail', 'field_brebo_comm_direction' => $direction,
      'field_brebo_comm_subject' => $subject, 'field_brebo_transcript' => $body,
      'field_brebo_comm_status' => 'Nieuw', 'field_brebo_formal_status' => 'Bron geregistreerd',
      'field_brebo_ai_status' => 'Niet verwerkt', 'field_brebo_intake_status' => 'Nieuw',
      'field_brebo_mail_from' => trim((string) ($mail['from'] ?? '')),
      'field_brebo_mail_to' => trim((string) ($mail['to'] ?? '')),
      'field_brebo_mail_classification' => trim((string) ($mail['classification'] ?? '')),
      'field_brebo_mail_html' => $htmlBody !== '' ? ['value' => $htmlBody, 'format' => 'brebo_mail_html'] : NULL,
      'field_brebo_match_basis' => trim((string) ($mail['match_basis'] ?? '')),
    ];

    $threadId = trim((string) ($mail['thread_id'] ?? ''));
    if ($threadId !== '') $values['field_brebo_conversation_id'] = $threadId;
    $receivedAt = trim((string) ($mail['received_at'] ?? ''));
    if ($receivedAt !== '') {
      $timestamp = strtotime($receivedAt);
      if ($timestamp === FALSE) throw new \InvalidArgumentException('received_at is geen geldige datum/tijd.');
      $values['field_brebo_comm_datetime'] = gmdate('Y-m-d\TH:i:s', $timestamp);
    }
    if (isset($mail['match_confidence']) && is_numeric($mail['match_confidence'])) $values['field_brebo_match_confidence'] = max(0, min(100, (float) $mail['match_confidence']));
    if (($id = (int) ($mail['suggested_building_id'] ?? 0)) > 0) $values['field_brebo_suggest_building_ref'] = ['target_id' => $id];
    if (($id = (int) ($mail['suggested_project_id'] ?? 0)) > 0) $values['field_brebo_suggest_project_ref'] = ['target_id' => $id];

    $node = $storage->create(array_filter($values, static fn(mixed $value): bool => $value !== NULL));
    if (!$node instanceof NodeInterface) throw new \RuntimeException('BREBO Communication kon niet worden aangemaakt.');
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Bronmail via Migrerende Mail Intake geregistreerd; koppelingen zijn nog niet formeel vastgesteld.');
    $node->save();
    $this->ensureMailboxProjection((int) $node->id(), $mail);
    return ['state' => 'created', 'node_id' => (int) $node->id(), 'duplicate_of' => NULL, 'source_hash' => $sourceHash];
  }

  /** Repairs the mailbox projection for an existing Communication node. */
  public function projectExisting(NodeInterface $node): bool {
    if ($node->bundle() !== 'brebo_communication') {
      return FALSE;
    }
    $field = static fn(NodeInterface $item, string $name): string => $item->hasField($name) ? trim((string) $item->get($name)->value) : '';
    return $this->ensureMailboxProjection((int) $node->id(), [
      'direction' => $field($node, 'field_brebo_comm_direction'),
      'from' => $field($node, 'field_brebo_mail_from'),
      'to' => $field($node, 'field_brebo_mail_to'),
    ]);
  }

  /** Determines direction against all registered active BREBO mailbox addresses. */
  private function mailDirection(array $mail): string {
    if (!$this->database->schema()->tableExists('brebo_mailbox')) {
      $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
      return in_array($direction, ['Inkomend', 'Uitgaand'], TRUE) ? $direction : 'Inkomend';
    }

    $own = $this->activeMailboxAddresses();
    if ($own === []) {
      $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
      return in_array($direction, ['Inkomend', 'Uitgaand'], TRUE) ? $direction : 'Inkomend';
    }

    if (array_intersect($this->emailAddresses((string) ($mail['from'] ?? '')), $own) !== []) {
      return 'Uitgaand';
    }
    if (array_intersect($this->emailAddresses((string) ($mail['to'] ?? '')), $own) !== []) {
      return 'Inkomend';
    }

    $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
    return in_array($direction, ['Inkomend', 'Uitgaand'], TRUE) ? $direction : 'Inkomend';
  }

  /** Projects one canonical Communication into each matching logical mailbox. */
  private function ensureMailboxProjection(int $communicationId, array $mail): bool {
    if (!$this->database->schema()->tableExists('brebo_mailbox') || !$this->database->schema()->tableExists('brebo_mailbox_message')) {
      return FALSE;
    }

    $from = $this->emailAddresses((string) ($mail['from'] ?? ''));
    $to = $this->emailAddresses((string) ($mail['to'] ?? ''));
    if ($from === [] && $to === []) {
      return FALSE;
    }

    $rows = $this->database->select('brebo_mailbox', 'mb')
      ->fields('mb', ['id', 'address'])
      ->condition('active', 1)
      ->execute()
      ->fetchAll();

    $projected = FALSE;
    foreach ($rows as $row) {
      $mailboxId = (int) $row->id;
      $address = strtolower(trim((string) $row->address));
      if ($address === '') {
        continue;
      }

      $mailState = NULL;
      if (in_array($address, $from, TRUE)) {
        $mailState = 'sent';
      }
      elseif (in_array($address, $to, TRUE)) {
        $mailState = 'inbox';
      }
      if ($mailState === NULL) {
        continue;
      }

      $this->database->merge('brebo_mailbox_message')
        ->keys(['mailbox_id' => $mailboxId, 'communication_id' => $communicationId])
        ->fields(['mail_state' => $mailState, 'is_read' => 0, 'is_starred' => 0, 'needs_action' => 0, 'changed' => time()])
        ->execute();
      $projected = TRUE;
    }

    return $projected;
  }

  /** @return string[] */
  private function activeMailboxAddresses(): array {
    $addresses = $this->database->select('brebo_mailbox', 'mb')
      ->fields('mb', ['address'])
      ->condition('active', 1)
      ->execute()
      ->fetchCol();
    return array_values(array_unique(array_filter(array_map(static fn(string $address): string => strtolower(trim($address)), $addresses ?: []))));
  }

  /** @return string[] */
  private function emailAddresses(string $value): array {
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
    $addresses = array_map(static fn(string $address): string => strtolower(trim($address)), $matches[0] ?? []);
    return array_values(array_unique(array_filter($addresses)));
  }

  /** Ensures the optional HTML body is available without changing other config. */
  private function ensureHtmlBodyField(): void {
    $storage = FieldStorageConfig::loadByName('node', 'field_brebo_mail_html');
    if (!$storage) {
      FieldStorageConfig::create([
        'uuid' => '6eb1d31f-bf56-4e1c-978a-69066ed4a9aa',
        'field_name' => 'field_brebo_mail_html',
        'entity_type' => 'node',
        'type' => 'text_long',
        'module' => 'text',
        'cardinality' => 1,
        'translatable' => TRUE,
      ])->save();
    }

    if (!FieldConfig::loadByName('node', 'brebo_communication', 'field_brebo_mail_html')) {
      FieldConfig::create([
        'uuid' => 'd56b52a1-e148-43c1-878e-2ca833414af9',
        'field_name' => 'field_brebo_mail_html',
        'entity_type' => 'node',
        'bundle' => 'brebo_communication',
        'label' => 'HTML-mailinhoud',
        'description' => 'Primaire HTML-body van de e-mail; transcript blijft de platte tekstfallback.',
        'required' => FALSE,
        'translatable' => TRUE,
      ])->save();
    }
  }

}
