<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Bridges project context to the canonical administration document identity. */
final class ProjectDocumentIdentityResolver {

  public function __construct(
    private readonly AdministrationContextResolver $context,
    private readonly AdministrationDocumentIdentity $identity,
  ) {}

  /** @return array<string, mixed> */
  public function forContextNode(int $nodeId): array {
    return $this->identity->forAdministration($this->context->codeForContextNode($nodeId));
  }

  /** @return array<string, mixed> */
  public function snapshotForContextNode(int $nodeId): array {
    return $this->identity->snapshot($this->context->codeForContextNode($nodeId));
  }

}
