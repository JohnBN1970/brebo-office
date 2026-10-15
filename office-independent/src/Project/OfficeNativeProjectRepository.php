<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use Throwable;

/** Native Office projects only. Never falls back to Drupal or a remote API. */
final class OfficeNativeProjectRepository {
  public function __construct(private readonly PDO $db) {}

  public function find(int $projectId): ?OfficeCanonicalProjectContext {
    if ($projectId < 1) {
      return null;
    }
    try {
      $statement = $this->db->prepare('SELECT id, code, title, status FROM office_projects WHERE id = :id LIMIT 1');
      $statement->execute([':id' => $projectId]);
      $project = $statement->fetch(PDO::FETCH_ASSOC);
      if (!is_array($project)) {
        return null;
      }
      return OfficeCanonicalProjectContext::fromPayload([
        'contract' => 'brebo-office-calc-context-v1',
        'project' => [
          'id' => (int) $project['id'],
          'code' => $project['code'],
          'title' => $project['title'],
          'status' => $project['status'],
        ],
      ], $projectId);
    } catch (Throwable $exception) {
      return null;
    }
  }
}
