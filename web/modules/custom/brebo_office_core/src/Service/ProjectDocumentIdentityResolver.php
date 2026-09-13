<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\node\NodeInterface;

/** Bridges project context to the canonical administration document identity. */
final class ProjectDocumentIdentityResolver {

  public function __construct(
    private readonly AdministrationContextResolver $context,
    private readonly AdministrationDocumentIdentity $identity,
  ) {}

  /** @return array<string, mixed> */
  public function forNode(NodeInterface $node): array {
    return $this->identity->forAdministration($this->context->codeForNode($node));
  }

  /** @return array<string, mixed> */
  public function snapshotForNode(NodeInterface $node): array {
    return $this->identity->snapshot($this->context->codeForNode($node));
  }

}
