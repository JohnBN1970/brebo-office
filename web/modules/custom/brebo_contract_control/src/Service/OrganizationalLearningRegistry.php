<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\OrganizationalLearningRepositoryInterface;

/** Stores approved organizational lessons as versioned BREBO knowledge. */
final class OrganizationalLearningRegistry {

  public function __construct(
    private readonly OrganizationalLearningRepositoryInterface $repository,
  ) {}

  /** @param array<string, mixed> $evidence
   *  @return array<string, mixed>
   */
  public function register(string $lessonCode, string $version, string $title, string $lesson, string $processChange, array $evidence, int $ownerUid, int $approvedBy, int $effectiveAt, int $reviewAt, ?int $now = NULL): array {
    $now ??= time();
    if (trim($lessonCode) === '' || trim($version) === '' || trim($title) === '' || trim($lesson) === '' || trim($processChange) === '') {
      throw new \InvalidArgumentException('Lescode, versie, titel, les en proceswijziging zijn verplicht.');
    }
    if ($ownerUid <= 0 || $approvedBy <= 0) {
      throw new \InvalidArgumentException('Eigenaar en goedkeurder zijn verplicht.');
    }
    if ($ownerUid === $approvedBy) {
      throw new \LogicException('Vier-ogenprincipe: eigenaar mag de eigen organisatieles niet zelf goedkeuren.');
    }
    if ($reviewAt <= $effectiveAt) {
      throw new \InvalidArgumentException('Herbeoordelingsdatum moet na de ingangsdatum liggen.');
    }
    if ($evidence === []) {
      throw new \InvalidArgumentException('Bronbewijs is verplicht voor een organisatieles.');
    }

    $id = $this->repository->insert([
      'lesson_code' => $lessonCode,
      'version' => $version,
      'title' => $title,
      'lesson' => $lesson,
      'process_change' => $processChange,
      'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
      'owner_uid' => $ownerUid,
      'approved_by' => $approvedBy,
      'status' => 'approved',
      'effective_at' => $effectiveAt,
      'review_at' => $reviewAt,
      'created_at' => $now,
    ]);

    return [
      'learning_id' => $id,
      'lesson_code' => $lessonCode,
      'version' => $version,
      'status' => 'approved',
      'effective_at' => $effectiveAt,
      'review_at' => $reviewAt,
    ];
  }

  /** @return array<int, array<string, mixed>> */
  public function dueForReview(?int $now = NULL): array {
    return $this->repository->findDueForReview($now ?? time());
  }

  /** @return array<int, array<string, mixed>> */
  public function history(string $lessonCode): array {
    return $this->repository->findHistory($lessonCode);
  }

}
