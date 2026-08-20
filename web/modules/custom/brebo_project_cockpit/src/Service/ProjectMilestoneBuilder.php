<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Builds the current project phase and next milestone from route items. */
final class ProjectMilestoneBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** @return array<string, mixed> */
  public function build(int $projectId, ?\DateTimeImmutable $now = NULL): array {
    $now ??= new \DateTimeImmutable('now');
    $storage = $this->entityTypeManager->getStorage('node');
    if ($this->entityTypeManager->getStorage('node_type')->load('brebo_route_item') === NULL) {
      return $this->empty();
    }

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_route_item')
      ->condition('field_brebo_project_ref', $projectId)
      ->sort('field_brebo_route_sequence', 'ASC')
      ->sort('field_brebo_route_due', 'ASC')
      ->execute();
    if ($ids === []) {
      return $this->empty();
    }

    $currentPhase = NULL;
    $next = NULL;
    $overdue = 0;
    $blocked = 0;
    $open = 0;
    $today = $now->format('Y-m-d');

    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof NodeInterface) {
        continue;
      }
      $status = $this->value($item, 'field_brebo_route_status');
      $done = in_array(mb_strtolower($status), ['gereed', 'n.v.t.', 'nvt'], TRUE);
      if ($done) {
        continue;
      }

      $open++;
      $phase = $this->value($item, 'field_brebo_lens_domain');
      if ($currentPhase === NULL && $phase !== '') {
        $currentPhase = $phase;
      }
      if (mb_strtolower($status) === 'geblokkeerd') {
        $blocked++;
      }

      $due = $this->value($item, 'field_brebo_route_due');
      if ($due !== '' && $due < $today) {
        $overdue++;
      }

      if ($next === NULL) {
        $owner = $item->hasField('field_brebo_route_owner') ? $item->get('field_brebo_route_owner')->entity : NULL;
        $next = [
          'id' => (int) $item->id(),
          'label' => (string) $item->label(),
          'kind' => $this->value($item, 'field_brebo_route_kind'),
          'phase' => $phase,
          'due' => $due !== '' ? $due : NULL,
          'status' => $status,
          'owner' => $owner ? (string) $owner->label() : NULL,
          'evidence' => $this->value($item, 'field_brebo_route_evidence'),
        ];
      }
    }

    $status = $blocked > 0 || $overdue > 0 ? 'rood' : ($open > 0 ? 'groen' : 'grijs');
    return [
      'current_phase' => $currentPhase,
      'next_milestone' => $next,
      'open_count' => $open,
      'overdue_count' => $overdue,
      'blocked_count' => $blocked,
      'status' => $status,
    ];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

  /** @return array<string, mixed> */
  private function empty(): array {
    return [
      'current_phase' => NULL,
      'next_milestone' => NULL,
      'open_count' => 0,
      'overdue_count' => 0,
      'blocked_count' => 0,
      'status' => 'grijs',
    ];
  }
}
