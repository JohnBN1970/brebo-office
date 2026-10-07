<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\AdministrationContextStoreInterface;
use Drupal\brebo_office_core\Contract\AdministrationNodeContextSourceInterface;

/** Resolves the legal/financial administration for project-derived content. */
final class AdministrationContextResolver {

  public function __construct(
    private readonly AdministrationRegistry $registry,
    private readonly AdministrationContextStoreInterface $store,
    private readonly AdministrationNodeContextSourceInterface $nodeContext,
  ) {}

  public function projectCode(int $projectId): string {
    if ($projectId <= 0) {
      throw new \InvalidArgumentException('A BREBO project id is required.');
    }
    $stored = $this->store->getProjectAdministrationCode($projectId);
    if ($stored !== '') {
      try {
        $this->registry->get($stored);
        return $stored;
      }
      catch (\InvalidArgumentException) {
        // A removed/renamed administration must never break an existing dossier.
      }
    }
    return $this->registry->primaryCode();
  }

  public function assignProject(int $projectId, string $code): void {
    if ($projectId <= 0) {
      throw new \LogicException('Save the project before persisting its administration assignment.');
    }
    $this->registry->get($code);
    $this->store->setProjectAdministrationCode($projectId, $code);
  }

  public function unassignProject(int $projectId): void {
    if ($projectId > 0) {
      $this->store->deleteProjectAdministrationCode($projectId);
    }
  }

  /** @return array<string, mixed> */
  public function forProject(int $projectId): array {
    return $this->registry->get($this->projectCode($projectId));
  }

  public function codeForContextNode(int $nodeId): string {
    $context = $this->nodeContext->context($nodeId);
    $projectId = (int) ($context['project_id'] ?? 0);
    return $projectId > 0 ? $this->projectCode($projectId) : $this->registry->primaryCode();
  }

  /** @return array<string, mixed> */
  public function forContextNode(int $nodeId): array {
    return $this->registry->get($this->codeForContextNode($nodeId));
  }

  public function projectIdForContextNode(int $nodeId): ?int {
    $context = $this->nodeContext->context($nodeId);
    $projectId = (int) ($context['project_id'] ?? 0);
    return $projectId > 0 ? $projectId : NULL;
  }

  public function bundleForContextNode(int $nodeId): ?string {
    $context = $this->nodeContext->context($nodeId);
    return isset($context['bundle']) ? (string) $context['bundle'] : NULL;
  }

}
