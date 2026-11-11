<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;

/** Read-only schedule lookup; accepts only project IDs already authorized by caller. */
final class OfficeProjectPlanReader {
  public static function enrich(PDO $db, array $projects): array {
    if ($projects === []) return [];
    $ids = array_values(array_unique(array_filter(array_map(
      static fn(array $p): int => (int)($p['id'] ?? 0), $projects
    ), static fn(int $id): bool => $id > 0)));
    if ($ids === []) return $projects;
    $table = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'office_project_plan_items'");
    if ((int)$table->fetchColumn() === 0) return $projects;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT project_id, planned_start, planned_end, status, critical_note
      FROM office_project_plan_items WHERE project_id IN ($placeholders)
      ORDER BY project_id, planned_start, id");
    $stmt->execute($ids);
    $byProject = [];
    $itemsByProject = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
      $id = (int)$item['project_id'];
      $itemsByProject[$id][] = $item;
      if (!isset($byProject[$id])) {
        $byProject[$id] = [
          'planned_start'=>(string)$item['planned_start'],
          'planned_end'=>(string)$item['planned_end'],
          'critical_note'=>'',
        ];
      }
      if ((string)$item['planned_start'] < $byProject[$id]['planned_start']) $byProject[$id]['planned_start'] = (string)$item['planned_start'];
      if ((string)$item['planned_end'] > $byProject[$id]['planned_end']) $byProject[$id]['planned_end'] = (string)$item['planned_end'];
      if (trim((string)($item['critical_note'] ?? '')) !== '' && $byProject[$id]['critical_note'] === '') {
        $byProject[$id]['critical_note'] = (string)$item['critical_note'];
      }
    }
    foreach ($projects as &$project) {
      if (isset($byProject[(int)$project['id']])) {
        $project = array_merge($project, $byProject[(int)$project['id']]);
        $project['plan_items'] = $itemsByProject[(int)$project['id']] ?? [];
      }
    }
    unset($project);
    return $projects;
  }
}
