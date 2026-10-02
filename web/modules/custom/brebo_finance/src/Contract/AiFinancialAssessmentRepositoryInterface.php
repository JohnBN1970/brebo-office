<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface AiFinancialAssessmentRepositoryInterface {
  /** @param array<string,mixed> $fields */
  public function createAssessment(array $fields): int;
  /** @return array<string,mixed>|null */
  public function assessment(int $id): ?array;
  /** @param array<string,mixed> $findingFields @param array<string,mixed> $assessmentFields */
  public function review(int $assessmentId,array $findingFields,array $assessmentFields,bool $createFinding): ?int;
}
