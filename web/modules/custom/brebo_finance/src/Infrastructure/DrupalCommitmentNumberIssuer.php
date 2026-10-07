<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CommitmentNumberIssuerInterface;
use Drupal\brebo_office_core\Service\ProjectDocumentNumberIssuer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use UnexpectedValueException;

final class DrupalCommitmentNumberIssuer implements CommitmentNumberIssuerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectDocumentNumberIssuer $documentNumberIssuer,
  ) {}

  public function issue(int $projectNid, int $commitmentId, int $year): array {
    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new UnexpectedValueException('A BREBO project is required for commitment numbering.');
    }

    return $this->documentNumberIssuer->issueAssignment($projectNid, (string) $commitmentId, $year);
  }

}
