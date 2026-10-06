<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\OutboundMailPersistenceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

final class DrupalOutboundMailPersistence implements OutboundMailPersistenceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function ensureOutboundFields(): void {
    foreach ([
      'field_brebo_mail_html' => ['text_long', 'text', '6eb1d31f-bf56-4e1c-978a-69066ed4a9aa', 'd56b52a1-e148-43c1-878e-2ca833414af9', 'HTML-mailinhoud', 'Veilig gefilterde HTML-variant van de e-mail.'],
      'field_brebo_mail_cc' => ['string_long', 'core', '0ab046bf-8494-44cd-aa03-4a4ffab9e531', '996e360c-e015-4861-9531-fd40d6db555a', 'CC', 'Zichtbare kopieontvangers van de e-mail.'],
      'field_brebo_mail_bcc' => ['string_long', 'core', 'd96dafc6-a3a4-4c25-b7b1-b98c46b75cd0', '326437b5-1f4f-4ae0-a95c-a2e0ba3d1888', 'BCC', 'Niet-zichtbare kopieontvangers; alleen voor gecontroleerde uitgaande verzending.'],
    ] as $fieldName => [$type, $module, $storageUuid, $fieldUuid, $label, $description]) {
      if (!FieldStorageConfig::loadByName('node', $fieldName)) {
        FieldStorageConfig::create([
          'uuid' => $storageUuid,
          'field_name' => $fieldName,
          'entity_type' => 'node',
          'type' => $type,
          'module' => $module,
          'cardinality' => 1,
          'translatable' => TRUE,
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'brebo_communication', $fieldName)) {
        FieldConfig::create([
          'uuid' => $fieldUuid,
          'field_name' => $fieldName,
          'entity_type' => 'node',
          'bundle' => 'brebo_communication',
          'label' => $label,
          'description' => $description,
          'required' => FALSE,
          'translatable' => TRUE,
        ])->save();
      }
    }
  }

  public function createDraft(
    array $draft,
    int $creatorUid,
    string $sourceId,
    string $revisionMessage,
  ): int {
    $values = [
      'type' => 'brebo_communication',
      'title' => '[CONCEPT] ' . (string) $draft['subject'],
      'uid' => $creatorUid,
      'status' => 1,
      'field_brebo_source_id' => $sourceId,
      'field_brebo_comm_channel' => 'E-mail',
      'field_brebo_comm_direction' => 'Uitgaand',
      'field_brebo_comm_subject' => (string) $draft['subject'],
      'field_brebo_transcript' => (string) $draft['body'],
      'field_brebo_mail_html' => (string) ($draft['body_html'] ?? ''),
      'field_brebo_mail_from' => (string) ($draft['from'] ?? ''),
      'field_brebo_mail_to' => (string) $draft['to'],
      'field_brebo_mail_cc' => (string) ($draft['cc'] ?? ''),
      'field_brebo_mail_bcc' => (string) ($draft['bcc'] ?? ''),
      'field_brebo_comm_status' => 'Concept',
      'field_brebo_formal_status' => 'Concept - goedkeuring vereist',
      'field_brebo_ai_status' => 'Concept',
      'field_brebo_intake_status' => 'Verwerkt',
    ];

    foreach ([
      'building_id' => 'field_brebo_building_ref',
      'project_id' => 'field_brebo_project_ref',
      'context_id' => 'field_brebo_comm_scope_target',
    ] as $input => $field) {
      $targetId = (int) ($draft[$input] ?? 0);
      if ($targetId > 0) {
        $values[$field] = ['target_id' => $targetId];
      }
    }

    $node = $this->entityTypeManager->getStorage('node')->create($values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Uitgaand communicatieconcept kon niet worden aangemaakt.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
    return (int) $node->id();
  }

  public function addRevisionNote(int $communicationId, string $revisionMessage): void {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      throw new \RuntimeException('BREBO Communication kon niet worden geladen.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
  }


  public function outboundMessage(int $communicationId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      return NULL;
    }

    return [
      'id' => (int) $node->id(),
      'direction' => trim((string) ($node->get('field_brebo_comm_direction')->value ?? '')),
      'formal_status' => trim((string) ($node->get('field_brebo_formal_status')->value ?? '')),
      'to' => trim((string) ($node->get('field_brebo_mail_to')->value ?? '')),
      'cc' => $node->hasField('field_brebo_mail_cc') ? trim((string) ($node->get('field_brebo_mail_cc')->value ?? '')) : '',
      'bcc' => $node->hasField('field_brebo_mail_bcc') ? trim((string) ($node->get('field_brebo_mail_bcc')->value ?? '')) : '',
      'subject' => trim((string) ($node->get('field_brebo_comm_subject')->value ?? '')),
      'body' => trim((string) ($node->get('field_brebo_transcript')->value ?? '')),
      'body_html' => $node->hasField('field_brebo_mail_html') ? trim((string) ($node->get('field_brebo_mail_html')->value ?? '')) : '',
    ];
  }

  public function markSent(int $communicationId, string $processedAt, string $revisionMessage): void {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      throw new \RuntimeException('BREBO Communication kon niet worden geladen.');
    }

    $node->set('field_brebo_comm_status', 'Verzonden');
    $node->set('field_brebo_formal_status', 'Verzonden');
    $node->set('field_brebo_processed_at', $processedAt);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
  }

}
