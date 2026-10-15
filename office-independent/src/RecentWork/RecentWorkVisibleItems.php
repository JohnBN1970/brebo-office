<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

/**
 * Converts untrusted persisted work rows to authorized events.
 * A row without current resource permission is never returned.
 */
final class RecentWorkVisibleItems {
  public function __construct(private readonly RecentWorkAccess $access) {}

  /**
   * @param array<int, array<string, mixed>> $rows
   * @return list<RecentWorkEvent>
   */
  public function filter(string $operatorId, array $rows): array {
    $visible = [];
    foreach ($rows as $row) {
      try {
        if (!is_array($row)
          || !is_string($row['module'] ?? null)
          || !is_string($row['resource_type'] ?? null)
          || !is_string($row['view_key'] ?? null)
          || !filter_var($row['resource_id'] ?? null, FILTER_VALIDATE_INT)) {
          continue;
        }
        $position = json_decode((string) ($row['position_json'] ?? '{}'), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($position)) {
          continue;
        }
        $chapter = $position['chapter_id'] ?? null;
        if ($chapter !== null && !filter_var($chapter, FILTER_VALIDATE_INT)) {
          continue;
        }
        $event = new RecentWorkEvent(
          $row['module'],
          $row['resource_type'],
          (int) $row['resource_id'],
          $row['view_key'],
          $chapter === null ? null : (int) $chapter,
        );
        if ($this->access->canAccess($operatorId, $event)) {
          $visible[] = $event;
        }
      } catch (\Throwable $e) {
        // Malformed or stale rows are ignored, not rendered.
      }
      if (count($visible) >= 5) {
        break;
      }
    }
    return $visible;
  }
}
