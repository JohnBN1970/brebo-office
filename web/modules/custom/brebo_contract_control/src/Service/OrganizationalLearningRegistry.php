<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Stores approved organizational lessons as versioned BREBO knowledge. */
final class OrganizationalLearningRegistry {

  public function __construct(private readonly Connection $database) {}

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

    $id = (int) $this->database->insert('brebo_organizational_learning')->fields([
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
    ])->execute();

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
    $now ??= time();
    return $this->database->select('brebo_organizational_learning', 'l')->fields('l')
      ->condition('status', 'approved')
      ->condition('review_at', $now, '<=')
      ->orderBy('review_at', 'ASC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function history(string $lessonCode): array {
    return $this->database->select('brebo_organizational_learning', 'l')->fields('l')
      ->condition('lesson_code', $lessonCode)
      ->orderBy('created_at', 'DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
