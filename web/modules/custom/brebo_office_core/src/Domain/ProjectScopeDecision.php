<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Domain;

/**
 * Immutable, source-backed decision about one project-scope subject.
 *
 * A decision never mutates or deletes its source evidence. Revisions supersede
 * earlier decisions so the current truth and its history remain auditable.
 */
final readonly class ProjectScopeDecision {

  public function __construct(
    public int $projectId,
    public string $subjectKey,
    public ProjectScopeStatementType $statementType,
    public ProjectScopeDisposition $disposition,
    public string $summary,
    public string $sourceType,
    public string $sourceRef,
    public ?int $sourceOccurredAt,
    public ?string $sourceExcerpt,
    public ?string $elementRef,
    public ?int $calculationFactId,
    public int $confirmedBy,
    public int $confirmedAt,
    public ?int $supersedesId = NULL,
  ) {
    if ($projectId <= 0) {
      throw new \InvalidArgumentException('Project id must be positive.');
    }
    if (trim($subjectKey) === '') {
      throw new \InvalidArgumentException('Scope subject key is required.');
    }
    if (trim($summary) === '') {
      throw new \InvalidArgumentException('Scope decision summary is required.');
    }
    if (trim($sourceType) === '' || trim($sourceRef) === '') {
      throw new \InvalidArgumentException('Scope decision source type and reference are required.');
    }
    if ($confirmedBy <= 0 || $confirmedAt <= 0) {
      throw new \InvalidArgumentException('Scope decision requires explicit human confirmation.');
    }
  }

}
