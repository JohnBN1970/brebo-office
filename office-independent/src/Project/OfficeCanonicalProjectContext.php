<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use InvalidArgumentException;

/** Verified shape only; does not grant operator access to a project. */
final class OfficeCanonicalProjectContext {
  public function __construct(
    public readonly OfficeProjectIdentity $identity,
    public readonly string $title,
    public readonly string $status,
  ) {}

  public static function fromPayload(array $payload, int $requestedProjectId): self {
    if (($payload['contract'] ?? null) !== 'brebo-office-calc-context-v1'
      || !isset($payload['project']) || !is_array($payload['project'])) {
      throw new InvalidArgumentException('Unexpected project context contract.');
    }
    $project = $payload['project'];
    $id = $project['id'] ?? null;
    if (!is_int($id) || $id < 1 || $id !== $requestedProjectId) {
      throw new InvalidArgumentException('Project context identity mismatch.');
    }
    foreach (['code', 'title', 'status'] as $key) {
      if (!isset($project[$key]) || !is_string($project[$key]) || strlen($project[$key]) > 255) {
        throw new InvalidArgumentException('Invalid project context field.');
      }
    }
    return new self(new OfficeProjectIdentity($id, $project['code']), $project['title'], $project['status']);
  }
}
