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

  public function createDraft(array $values, string $revisionMessage): int {
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

}
