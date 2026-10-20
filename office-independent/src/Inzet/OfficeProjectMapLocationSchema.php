<?php
declare(strict_types=1);
/**
 * Review-only schema proposal. Do not execute automatically against production.
 * Project coordinates are optional; null means no verified geographic start point.
 */
final class OfficeProjectMapLocationSchema {
  public static function reviewSql(): array {
    return [
      'ALTER TABLE office_projects ADD COLUMN latitude DECIMAL(10,7) NULL',
      'ALTER TABLE office_projects ADD COLUMN longitude DECIMAL(10,7) NULL',
    ];
  }
}
