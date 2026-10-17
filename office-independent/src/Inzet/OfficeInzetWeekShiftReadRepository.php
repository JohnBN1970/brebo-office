<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Read-only weekly shifts, scoped by active Office membership in SQL.
 * No migration, writes, or Drupal dependencies.
 */
final class OfficeInzetWeekShiftReadRepository {
  public function __construct(private PDO $db) {}

  /** @param list<int> $authorizedProjectIds
   * @return list<array<string,mixed>>
   */
  public function listWeek(int $operatorId, array $authorizedProjectIds, int $isoYear, int $isoWeek): array {
    if ($operatorId < 1) throw new \InvalidArgumentException('Invalid operator');
    $projects = array_values(array_unique(array_filter(
      $authorizedProjectIds, static fn(mixed $id): bool => is_int($id) && $id > 0
    )));
    if ($projects === []) return [];
    $utc = new DateTimeZone('UTC');
    $monday = (new DateTimeImmutable('now', $utc))->setISODate($isoYear, $isoWeek, 1)->setTime(0, 0);
    if ($isoYear < 2020 || $isoYear > 2100 || $isoWeek < 1 || $isoWeek > 53
      || (int)$monday->format('o') !== $isoYear || (int)$monday->format('W') !== $isoWeek) {
      throw new \InvalidArgumentException('Invalid ISO week');
    }
    $until = $monday->modify('+7 days');
    $params = [
      ':operator' => $operatorId,
      ':week_start' => $monday->format('Y-m-d H:i:s'),
      ':week_end' => $until->format('Y-m-d H:i:s'),
    ];
    $holders = [];
    foreach ($projects as $index => $id) {
      $key = ':project' . $index;
      $holders[] = $key;
      $params[$key] = $id;
    }
    $sql = 'SELECT s.id, s.project_id, s.person_id, s.starts_at, s.ends_at, s.status
      FROM office_inzet_shifts s
      WHERE s.project_id IN (' . implode(',', $holders) . ')
        AND s.starts_at < :week_end AND s.ends_at > :week_start
        AND EXISTS (
          SELECT 1 FROM office_project_memberships m
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE m.operator_id = :operator AND m.project_id = s.project_id
            AND m.permission IN (\'read\',\'manage\') AND a.active = 1
        )
      ORDER BY s.starts_at ASC, s.id ASC LIMIT 500';
    $statement = $this->db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}
