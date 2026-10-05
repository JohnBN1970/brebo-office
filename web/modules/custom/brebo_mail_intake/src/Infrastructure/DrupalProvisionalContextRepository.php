<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\ProvisionalContextRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for provisional mail context persistence. */
final class DrupalProvisionalContextRepository implements ProvisionalContextRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function communication(int $communicationId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      return NULL;
    }

    return [
      'id' => (int) $node->id(),
      'subject' => $this->fieldValue($node, 'field_brebo_comm_subject'),
      'from' => $this->fieldValue($node, 'field_brebo_mail_from'),
      'received_at' => $this->fieldValue($node, 'field_brebo_comm_datetime'),
    ];
  }

  public function createProject(array $values, string $revisionMessage): int {
    return $this->createNode('brebo_project', $values, $revisionMessage);
  }

  public function createBuilding(array $values, string $revisionMessage): int {
    return $this->createNode('brebo_building', $values, $revisionMessage);
  }

  /** @param array<string,mixed> $values */
  private function createNode(string $bundle, array $values, string $revisionMessage): int {
    $node = $this->entityTypeManager->getStorage('node')->create(['type' => $bundle] + $values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('%s kon niet worden aangemaakt.', $bundle));
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
    return (int) $node->id();
  }

  private function fieldValue(NodeInterface $node, string $fieldName): string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    return trim((string) $node->get($fieldName)->value);
  }

}
