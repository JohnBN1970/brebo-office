<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Projection;

use Drupal\brebo_project_cockpit\Service\ProjectMilestoneBuilder;
use Drupal\brebo_project_cockpit\Service\ProjectProgressBuilder;
use Drupal\node\NodeInterface;

/** Builds allowlist-ready customer projections from canonical project data. */
final class ProjectPortalProjectionBuilder {

  public function __construct(
    private ProjectProgressBuilder $progressBuilder,
    private ProjectMilestoneBuilder $milestoneBuilder,
  ) {}

  /** @return array<string, array<string, mixed>> */
  public function build(NodeInterface $project, array $portalProject): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Only canonical BREBO projects can be projected.');
    }

    $projectId = (int) $project->id();
    $progress = $this->progressBuilder->build($projectId);
    $milestones = $this->milestoneBuilder->build($projectId);
    $next = is_array($milestones['next_milestone'] ?? NULL) ? $milestones['next_milestone'] : [];

    $publicTitle = trim((string) ($portalProject['public_title'] ?? ''));
    $publicStatus = trim((string) ($portalProject['public_status'] ?? ''));
    $location = $this->fieldValue($project, 'field_brebo_location');

    return [
      'project_summary' => [
        'name' => $publicTitle !== '' ? $publicTitle : (string) $project->label(),
        'address' => $location,
        'status' => $publicStatus,
        'message' => '',
        'last_updated' => gmdate('c'),
      ],
      'progress' => [
        'percentage' => $progress['actual_progress_pct'] ?? NULL,
        'label' => $this->publicProgressLabel($progress),
        'last_updated' => gmdate('c'),
      ],
      'milestone' => [
        'title' => isset($next['label']) ? (string) $next['label'] : '',
        'period' => isset($next['due']) && $next['due'] !== NULL ? (string) $next['due'] : '',
        'status' => isset($next['status']) ? (string) $next['status'] : '',
      ],
    ];
  }

  /** @param array<string, mixed> $progress */
  private function publicProgressLabel(array $progress): string {
    $percentage = $progress['actual_progress_pct'] ?? NULL;
    if (!is_numeric($percentage)) {
      return 'Voortgang nog niet beschikbaar';
    }
    return sprintf('%.0f%% gereed', (float) $percentage);
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

}
