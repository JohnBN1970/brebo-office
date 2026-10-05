<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailProposalRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for unpublished mail-derived proposals. */
final class DrupalMailProposalRepository implements MailProposalRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function communicationContext(int $communicationId): ?array {
    $communication = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$communication instanceof NodeInterface || $communication->bundle() !== 'brebo_communication') {
      return NULL;
    }

    return [
      'communication_id' => (int) $communication->id(),
      'building_id' => $this->targetId($communication, 'field_brebo_building_ref'),
      'project_id' => $this->targetId($communication, 'field_brebo_project_ref'),
      'context_id' => $this->targetId($communication, 'field_brebo_comm_scope_target'),
    ];
  }

  public function createProposal(
    string $bundle,
    string $title,
    int $actorId,
    array $context,
    array $fields,
    string $revisionMessage,
  ): int {
    $values = [
      'type' => $bundle,
      'title' => $title,
      'uid' => $actorId,
      'status' => 0,
      'field_brebo_source_comm_ref' => ['target_id' => (int) $context['communication_id']],
      'field_brebo_responsible_user' => ['target_id' => $actorId],
    ];

    if (!empty($context['building_id'])) {
      $values['field_brebo_building_ref'] = ['target_id' => (int) $context['building_id']];
    }
    if (!empty($context['project_id'])) {
      $values['field_brebo_project_ref'] = ['target_id' => (int) $context['project_id']];
    }
    if (!empty($context['context_id'])) {
      $values['field_brebo_context_ref'] = ['target_id' => (int) $context['context_id']];
    }

    foreach ($fields as $field => $value) {
      $values[$field] = $value;
    }

    $node = $this->entityTypeManager->getStorage('node')->create($values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('%s-voorstel kon niet worden aangemaakt.', $bundle));
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();

    return (int) $node->id();
  }

  private function targetId(NodeInterface $node, string $field): ?int {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return NULL;
    }
    $id = (int) ($node->get($field)->target_id ?? 0);
    return $id > 0 ? $id : NULL;
  }

}
