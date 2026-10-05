<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface ClassificationRepositoryInterface {
  public function upsert(string $systemKey, string $sourceVersion, string $code, string $label, ?string $parentCode = NULL, int $level = 0, ?int $sourceRecordId = NULL): int;
  /** @return array<int,array<string,mixed>> */
  public function allForVersion(string $systemKey, string $sourceVersion): array;
  /** @return string[] */
  public function versions(string $systemKey): array;
  /** @return array<string,mixed>|null */
  public function findByCode(string $systemKey, string $sourceVersion, string $code): ?array;
  /** @return array<int,array<string,mixed>> */
  public function search(string $systemKey, string $sourceVersion, string $search = '', int $limit = 50): array;
}
