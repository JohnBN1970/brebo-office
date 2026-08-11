<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/** Registers normalized mail as source evidence in Communication. */
final class MailIntakeIngestor {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function ingest(array $mail): array {
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
      return ['state' => 'duplicate', 'node_id' => $existingId, 'duplicate_of' => $existingId, 'source_hash' => $sourceHash];
    }

    $ownerUid = (int) (getenv('BREBO_MAIL_INTAKE_UID') ?: $this->currentUser->id());
    if ($ownerUid <= 0 || !$this->entityTypeManager->getStorage('user')->load($ownerUid)) {
      throw new \RuntimeException('BREBO_MAIL_INTAKE_UID ontbreekt of verwijst niet naar een geldige Drupal-gebruiker.');
    }
    $direction = trim((string) ($mail['direction'] ?? 'Inkomend'));
    if (!in_array($direction, ['Inkomend', 'Uitgaand'], TRUE)) {
      $direction = 'Inkomend';
    }

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

    $node = $storage->create($values);
    if (!$node instanceof NodeInterface) throw new \RuntimeException('BREBO Communication kon niet worden aangemaakt.');
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Bronmail via Migrerende Mail Intake geregistreerd; koppelingen zijn nog niet formeel vastgesteld.');
    $node->save();
    return ['state' => 'created', 'node_id' => (int) $node->id(), 'duplicate_of' => NULL, 'source_hash' => $sourceHash];
  }

}
