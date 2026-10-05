<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailCommunicationRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/** Drupal adapter for canonical Communication intake persistence. */
final class DrupalMailCommunicationRepository implements MailCommunicationRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function ensureHtmlBodyField(): void {
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

  public function duplicateCommunicationId(string $sourceId, string $sourceHash): ?int {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_communication');
    $or = $query->orConditionGroup()
      ->condition('field_brebo_source_id', $sourceId)
      ->condition('field_brebo_source_hash', $sourceHash);
    $ids = $query->condition($or)->range(0, 1)->execute();
    return $ids ? (int) reset($ids) : NULL;
  }

  public function defaultOwnerId(): int {
    return (int) $this->currentUser->id();
  }

  public function validUser(int $userId): bool {
    return $userId > 0 && $this->entityTypeManager->getStorage('user')->load($userId) !== NULL;
  }

  public function createCommunication(array $values, string $revisionMessage): int {
    $node = $this->entityTypeManager->getStorage('node')->create($values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('BREBO Communication kon niet worden aangemaakt.');
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
    return (int) $node->id();
  }

  public function projectionSource(int $communicationId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      return NULL;
    }

    return [
      'direction' => $this->fieldValue($node, 'field_brebo_comm_direction'),
      'from' => $this->fieldValue($node, 'field_brebo_mail_from'),
      'to' => $this->fieldValue($node, 'field_brebo_mail_to'),
    ];
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    return $node->hasField($field) ? trim((string) ($node->get($field)->value ?? '')) : '';
  }

}
